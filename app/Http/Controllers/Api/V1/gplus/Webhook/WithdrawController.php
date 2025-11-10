<?php

namespace App\Http\Controllers\Api\V1\gplus\Webhook;

use App\Enums\SeamlessWalletCode;
use App\Enums\TransactionName;
use App\Http\Controllers\Controller;
use App\Models\GameList;
use App\Models\PlaceBet;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\CustomWalletService;
use App\Models\CustomTransaction;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawController extends Controller
{
    protected CustomWalletService $customWalletService;

    /**
     * @var array Allowed currencies for withdraw.
     */
    private array $allowedCurrencies = ['MMK', 'IDR', 'IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];

    /**
     * @var array Currencies requiring special conversion (e.g., 1:1000).
     */
    private array $specialCurrencies = ['IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];

    /**
     * @var array Actions considered as debits/withdrawals.
     */
    private array $debitActions = ['BET', 'ADJUST_DEBIT', 'WITHDRAW', 'FEE']; // Add other debit-like actions

    /**
     * WithdrawController constructor.
     */
    public function __construct(CustomWalletService $customWalletService)
    {
        $this->customWalletService = $customWalletService;
    }

    /**
     * Handle incoming withdraw/bet requests.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw(Request $request)
    {
        Log::debug('=== DEBUGGING BALANCE ISSUE - WithdrawController ===');
        Log::debug('WithdrawController: Incoming Request', ['request' => $request->all()]);
        
        try {
            $request->validate([
                'operator_code' => 'required|string',
                'batch_requests' => 'required|array',
                'sign' => 'required|string',
                'request_time' => 'required|integer',
                'currency' => 'required|string',
            ]);
            Log::info('Withdraw API Request', ['request' => $request->all()]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Withdraw API Validation Failed', ['errors' => $e->errors()]);

            return ApiResponseService::error(
                SeamlessWalletCode::InternalServerError,
                'Validation failed',
                $e->errors()
            );
        }

        // Process all transactions in the batch
        Log::debug('WithdrawController: Starting processWithdrawTransactions');
        $results = $this->processWithdrawTransactions($request);
        Log::debug('WithdrawController: processWithdrawTransactions completed', ['results' => $results]);

        // Log the overall batch request and its final outcome
        // This provides an audit trail for the entire webhook call
        \App\Models\TransactionLog::create([ // Use full namespace to avoid alias conflict if any
            'type' => 'withdraw',
            'batch_request' => $request->all(),
            'response_data' => $results,
            'status' => collect($results)->every(fn ($r) => $r['code'] === SeamlessWalletCode::Success->value) ? 'success' : 'partial_success_or_failure',
        ]);

        Log::debug('WithdrawController: Final API response', ['response' => $results]);
        return ApiResponseService::success($results);
    }

    /**
     * Centralized logic for processing seamless wallet withdrawal/bet transactions.
     */
    private function processWithdrawTransactions(Request $request): array
    {
        $secretKey = Config::get('seamless_key.secret_key');

        $expectedSign = md5(
            $request->operator_code.
            $request->request_time.
            'withdraw'. // Ensure this matches the string used in the provider's signature generation
            $secretKey
        );
        $isValidSign = strtolower($request->sign) === strtolower($expectedSign);
        $isValidCurrency = in_array($request->currency, $this->allowedCurrencies);

        $responseData = [];

        foreach ($request->batch_requests as $batchRequest) {
            Log::info('Withdraw Batch Request', ['batchRequest' => $batchRequest]);

            $memberAccount = $batchRequest['member_account'] ?? null;
            $productCode = $batchRequest['product_code'] ?? null;
            $gameType = $batchRequest['game_type'] ?? '';

            // Initialize variables for the current batch request's user and balance
            $user = null;
            $currentBalance = 0.0;
            $beforeTransactionBalance = 0.0; // Initialize for scope in catch blocks

            // Handle batch-level errors (invalid signature or currency)
            if (! $isValidSign) {
                Log::warning('Invalid signature for batch', ['member_account' => $memberAccount, 'provided' => $request->sign, 'expected' => $expectedSign]);
                $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, 0.0, SeamlessWalletCode::InvalidSignature, 'Invalid signature', $request->currency);
                continue;
            }

            if (! $isValidCurrency) {
                Log::warning('Invalid currency for batch', ['member_account' => $memberAccount, 'currency' => $request->currency]);
                $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, 0.0, SeamlessWalletCode::InternalServerError, 'Invalid Currency', $request->currency);
                continue;
            }

            // User and Wallet Lookup for the entire batch request
            try {
                $user = User::where('user_name', $memberAccount)->first();

                if (! $user) {
                    Log::warning('Member not found for withdraw/bet request', ['member_account' => $memberAccount]);
                    $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, 0.00, SeamlessWalletCode::MemberNotExist, 'Member not found', $request->currency);
                    continue; // Skip to next batch request
                }

                // Check if user has a valid balance (can be 0, but should be numeric)
                if (!is_numeric($user->balance)) {
                    Log::warning('Invalid balance for member during withdraw/bet request', ['member_account' => $memberAccount, 'balance' => $user->balance]);
                    $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, 0.00, SeamlessWalletCode::MemberNotExist, 'Invalid user balance', $request->currency);
                    continue; // Skip to next batch request
                }

                $initialBalance = $user->balance; // Get initial balance before processing any transactions in this batch
                $currentBalance = $initialBalance; // This will track balance changes within the batch for accurate reporting

            } catch (\Throwable $e) {
                Log::error('Initial user/wallet lookup exception for member in WithdrawController', ['member_account' => $memberAccount, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $responseData[] = $this->buildErrorResponse(
                    $memberAccount,
                    $productCode,
                    0.0,
                    SeamlessWalletCode::InternalServerError,
                    'An unexpected error occurred during initial lookup: '.$e->getMessage(),
                    $request->currency
                );
                continue; // Skip to next batch request if user/wallet cannot be found
            }

            // Iterate through individual transactions within the batch
                foreach ($batchRequest['transactions'] ?? [] as $tx) {
                    $transactionId = $tx['id'] ?? null;
                    $action = strtoupper($tx['action'] ?? '');
                    $amount = floatval($tx['amount'] ?? 0); // Ensure amount is float for calculations
                    $wagerCode = $tx['wager_code'] ?? $tx['round_id'] ?? null;
                    $gameCode = $tx['game_code'] ?? null;
                $transactionCode = SeamlessWalletCode::Success->value; // Default to success
                $transactionMessage = 'Transaction processed successfully';

                    // Determine game_type, prioritizing batchRequest, then DB lookup
                    $transactionGameType = $batchRequest['game_type'] ?? null;
                    if (empty($transactionGameType) && $gameCode) {
                        $transactionGameType = GameList::where('game_code', $gameCode)->value('game_type');
                    }

                    if (empty($transactionGameType)) {
                        Log::warning('Missing game_type from batch_request and fallback lookup for withdraw', [
                            'member_account' => $memberAccount,
                            'product_code' => $productCode,
                            'game_code' => $gameCode,
                            'transaction_id' => $transactionId,
                        ]);
                        $responseData[] = $this->buildErrorResponse(
                            $memberAccount,
                            $productCode,
                            $currentBalance,
                            SeamlessWalletCode::InternalServerError,
                            'Missing game_type',
                            $request->currency
                        );
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                        $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Missing game_type', $currentBalance, $currentBalance);
                        continue;
                    }

                    // Check for crucial missing data for an individual transaction
                    if (! $transactionId || empty($action)) { // Amount can be 0 or negative for certain actions
                        Log::warning('Missing crucial data in transaction for withdraw/bet', ['tx' => $tx]);
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                        $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Missing transaction data (id or action)', $currentBalance, $currentBalance);
                        $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, $currentBalance, SeamlessWalletCode::InternalServerError, 'Missing transaction data (id or action)', $request->currency);
                        continue;
                    }

                    // Convert amount based on currency for internal processing (always positive for withdrawal amount)
                    $convertedAmount = abs($this->toDecimalPlaces($amount * $this->getCurrencyValue($request->currency)));

                    // Meta data for wallet transaction and logging
                    $meta = [
                        'seamless_transaction_id' => $transactionId,
                        'action_type' => $action,
                        'product_code' => $productCode,
                        'wager_code' => $wagerCode,
                        'round_id' => $tx['round_id'] ?? null,
                        'game_code' => $gameCode,
                        'game_type' => $transactionGameType, // Ensure game_type is passed to meta
                        'channel_code' => $tx['channel_code'] ?? null,
                        'raw_payload' => $tx,
                    ];

                    // Check for duplicate transactions (idempotency)
                    $isDuplicate = PlaceBet::where('transaction_id', $transactionId)->exists() ||
                               CustomTransaction::whereJsonContains('meta->seamless_transaction_id', $transactionId)->exists();

                    if ($isDuplicate) {
                        Log::warning('Duplicate transaction ID detected for withdraw/bet', ['tx_id' => $transactionId, 'member_account' => $memberAccount, 'action' => $action]);
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                        $this->logPlaceBet($batchRequest, $request, $tx, 'duplicate', $request->request_time, 'Duplicate transaction', $currentBalance, $currentBalance);
                        $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, $currentBalance, SeamlessWalletCode::DuplicateTransaction, 'Duplicate transaction', $request->currency);
                        continue;
                    }

                    // Ensure action is a valid debit action for this controller
                    if (! in_array($action, $this->debitActions)) {
                        Log::warning('Unsupported action type received on withdraw endpoint', ['transaction_id' => $transactionId, 'action' => $action]);
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                        $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Unsupported action type for this endpoint: '.$action, $currentBalance, $currentBalance);
                        $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, $currentBalance, SeamlessWalletCode::InternalServerError, 'Unsupported action type: '.$action, $request->currency);
                    continue;
                }

                // If amount is zero or negative for a debit action, handle it as success but no balance change
                if ($convertedAmount <= 0) {
                    Log::info('WithdrawController: Processing debit action with zero/negative amount', [
                        'member_account' => $memberAccount,
                        'action' => $action,
                        'amount' => $amount,
                        'convertedAmount' => $convertedAmount,
                    ]);
                    $transactionMessage = 'Debit action with zero/negative amount.';
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                    $this->logPlaceBet($batchRequest, $request, $tx, 'info', $request->request_time, $transactionMessage, $currentBalance, $currentBalance);
                    $formattedBalance = $this->formatBalanceForResponse($currentBalance, $request->currency);
                    
                    $responseData[] = [
                        'member_account' => $memberAccount,
                        'product_code' => (int) $productCode,
                        'before_balance' => $formattedBalance,
                        'balance' => $formattedBalance, // Balance doesn't change
                        'code' => SeamlessWalletCode::Success->value, // Still success for processing the request
                        'message' => 'Processed with zero amount, no balance change.',
                    ];
                        continue;
                    }

                    // Transaction-specific logic
                    DB::beginTransaction();
                    try {
                        // Re-fetch user with wallet lock for concurrency safety
                        $user->refresh(); // Refresh user model to get latest balance
                    $userWithWallet = User::where('id', $user->id)->lockForUpdate()->first();

                    if (!$userWithWallet) {
                        throw new Exception('User not found during transaction locking.');
                    }

                    $beforeTransactionBalance = $userWithWallet->balance;

                    Log::info('WithdrawController: Checking balance for insufficient funds (pre-deduction)', [
                            'member_account' => $memberAccount,
                        'current_balance' => $userWithWallet->balance,
                        'amount_to_deduct' => $convertedAmount,
                        'action' => $action
                    ]);

                    // Calculate projected balance after this transaction
                    $projectedBalance = $beforeTransactionBalance - $convertedAmount;

                    // Define your minimum balance threshold here
                    $minimumAllowedBalance = 1000.00; // Use float for comparison if balances are floats

                    // Check for insufficient funds based on *projected* balance falling below *zero*
                    // OR falling below your defined minimum allowed balance (e.g., 1000)
                    // if ($projectedBalance < 0 || $projectedBalance < $minimumAllowedBalance) {
                    //     Log::warning('WithdrawController: Insufficient balance detected (projected balance too low)', [
                    //         'member_account' => $memberAccount,
                    //         'current_balance' => $beforeTransactionBalance,
                    //         'amount_to_deduct' => $convertedAmount,
                    //         'projected_balance' => $projectedBalance,
                    //         'minimum_allowed_balance' => $minimumAllowedBalance
                    //     ]);
                    //     // Manually set response for insufficient balance
                    //     $responseData[] = [
                    //         'member_account' => $memberAccount,
                    //         'product_code' => (int) $productCode,
                    //         'before_balance' => $this->formatBalance($beforeTransactionBalance, $request->currency),
                    //         'balance' => $this->formatBalance($beforeTransactionBalance, $request->currency), // Balance doesn't change
                    //         'code' => SeamlessWalletCode::InsufficientBalance->value,
                    //         'message' => 'Insufficient Balance',
                    //     ];
                    //     DB::commit(); // Commit the transaction to release locks, but no wallet change
                    //     // Pass $memberAccount to logPlaceBet to ensure it's logged
                    //     $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Insufficient Balance', $beforeTransactionBalance, $beforeTransactionBalance);
                    //     continue; // Skip to next transaction in batch
                    // }

                        if ($beforeTransactionBalance < $convertedAmount) {
                            $transactionCode = SeamlessWalletCode::InsufficientBalance->value;
                            $transactionMessage = 'Insufficient balance';
                            $this->logPlaceBet(
                                $batchRequest, $request, $tx, 'failed',
                                $request->request_time, $transactionMessage,
                                $beforeTransactionBalance, $beforeTransactionBalance
                            );
                            DB::rollBack(); // Or commit, but rollback is more "traditional" if nothing changed
                            $formattedBalance = $this->formatBalanceForResponse($beforeTransactionBalance, $request->currency);
                            
                            $responseData[] = [
                                'member_account' => $memberAccount,
                                'product_code' => (int) $productCode,
                                'before_balance' => $formattedBalance,
                                'balance' => $formattedBalance,
                                'code' => $transactionCode,
                                'message' => $transactionMessage,
                            ];

                            continue;
                        }

                    // Perform the withdrawal through wallet service
                    // Bavix\Wallet's withdraw method will throw InsufficientFunds if the internal balance check fails
                        $this->customWalletService->withdraw($userWithWallet, $convertedAmount, TransactionName::Withdraw, $meta);
                    
                    // Refresh the user model to get the updated balance
                    $userWithWallet->refresh();
                    $newBalance = $userWithWallet->balance;

                        $transactionCode = SeamlessWalletCode::Success->value;
                        $transactionMessage = 'Transaction processed successfully';
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                        $this->logPlaceBet($batchRequest, $request, $tx, 'completed', $request->request_time, $transactionMessage, $beforeTransactionBalance, $newBalance);

                        // Add success response with proper decimal formatting
                        $beforeBalanceValue = $this->formatBalanceForResponse($beforeTransactionBalance, $request->currency);
                        $afterBalanceValue = $this->formatBalanceForResponse($newBalance, $request->currency);

                        $responseData[] = [
                            'member_account' => $memberAccount,
                            'product_code' => (int) $productCode,
                            'before_balance' => $beforeBalanceValue,
                            'balance' => $afterBalanceValue,
                            'code' => $transactionCode,
                            'message' => $transactionMessage,
                            'transaction_id' => $transactionId,
                        ];

                        DB::commit();
                        $currentBalance = $newBalance; // Update current balance for next transaction in the batch

                } catch (QueryException $e) { // Catch database-specific errors
                    DB::rollBack();
                    $transactionCode = SeamlessWalletCode::InternalServerError->value;
                    $transactionMessage = 'Database error during transaction: '.$e->getMessage();
                    Log::error('Database Error processing withdraw/bet transaction', ['transaction_id' => $transactionId, 'action' => $action, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                    $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, $transactionMessage, $beforeTransactionBalance, $currentBalance);

                } catch (Exception $e) { // Catch all other generic exceptions
                        DB::rollBack();
                    // Check if it's an insufficient balance error
                    if (str_contains($e->getMessage(), 'Insufficient balance')) {
                        $transactionCode = SeamlessWalletCode::InsufficientBalance->value;
                        $transactionMessage = 'Insufficient balance: '.$e->getMessage();
                        Log::warning('Insufficient Funds for withdraw/bet', ['transaction_id' => $transactionId, 'member_account' => $memberAccount, 'amount' => $amount, 'error' => $e->getMessage()]);
                    } else {
                        $transactionCode = SeamlessWalletCode::InternalServerError->value;
                        $transactionMessage = 'Failed to process transaction: '.$e->getMessage();
                        Log::error('Error processing withdraw/bet transaction', ['transaction_id' => $transactionId, 'action' => $action, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    }
                    // Pass $memberAccount to logPlaceBet to ensure it's logged
                    $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, $transactionMessage, $beforeTransactionBalance, $currentBalance);
                    }

            }
        }

        return $responseData;
    }

    /**
     * Helper to build a consistent error response.
     */
    private function buildErrorResponse(string $memberAccount, string|int $productCode, float $balance, SeamlessWalletCode $code, string $message, string $currency): array
    {
        $formattedBalanceValue = $this->formatBalanceForResponse($balance, $currency);
        
        return [
            'member_account' => $memberAccount,
            'product_code' => (int) $productCode, // Ensure it's an int
            'before_balance' => $formattedBalanceValue,
            'balance' => $formattedBalanceValue,
            'code' => $code->value,
            'message' => $message,
        ];
    }

    /**
     * Converts a float to a specified number of decimal places.
     */
    private function toDecimalPlaces(float $value, int $precision = 4): float
    {
        return round($value, $precision);
    }

    /**
     * Gets the currency conversion value for internal processing.
     * This is the multiplier to convert external API amount to internal base unit.
     */
    private function getCurrencyValue(string $currency): int|float
    {
        return match ($currency) {
            'IDR2' => 100,
            'KRW2' => 10,
            'MMK2' => 1000, // Assuming MMK2 implies external unit * 1000 = internal unit
            'VND2' => 1000,
            'LAK2' => 10,
            'KHR2' => 100,
            default => 1,
        };
    }

    /**
     * Format balance for response (matching GetBalanceController logic)
     */
    private function formatBalanceForResponse(float $balance, string $currency): float
    {
        $specialCurrencies = ['IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];
        $isSpecialCurrency = in_array($currency, $specialCurrencies);
        
        Log::debug('WithdrawController: formatBalanceForResponse', [
            'input_balance' => $balance,
            'currency' => $currency,
            'isSpecialCurrency' => $isSpecialCurrency,
            'conversion_divisor' => $isSpecialCurrency ? 1000 : 1,
            'decimal_places' => $isSpecialCurrency ? 4 : 2
        ]);
        
        if ($isSpecialCurrency) {
            $balance = $balance / 1000; // Apply 1:1000 conversion here (matching working version)
            $balance = round($balance, 4);
        } else {
            $balance = round($balance, 2);
        }
        
        Log::debug('WithdrawController: formatBalanceForResponse result', [
            'output_balance' => $balance,
            'output_type' => gettype($balance),
            'json_encoded' => json_encode($balance)
        ]);
        
        return (float) $balance;
    }

    /**
     * Logs the transaction attempt in the place_bets table.
     *
     * @param  array  $batchRequest The current batch request being processed.
     * @param  Request  $fullRequest The full incoming HTTP request.
     * @param  array  $transactionRequest The individual transaction details from the batch.
     * @param  string  $status The status of the transaction ('completed', 'failed', 'duplicate', 'info', 'loss').
     * @param  int|null  $requestTime The original request_time from the full request (milliseconds).
     * @param  string|null  $errorMessage Optional error message.
     * @param  float|null  $beforeBalance Optional balance before the transaction.
     * @param  float|null  $afterBalance Optional balance after the transaction.
     */
    private function logPlaceBet(
        array $batchRequest,
        Request $fullRequest,
        array $transactionRequest,
        string $status,
        ?int $requestTime,
        ?string $errorMessage = null,
        ?float $beforeBalance = null,
        ?float $afterBalance = null
    ): void {
        // Convert milliseconds to seconds if necessary for timestamp columns
        $requestTimeInSeconds = $requestTime ? floor($requestTime / 1000) : null;
        $settleAtTime = $transactionRequest['settle_at'] ?? $transactionRequest['settled_at'] ?? null;
        $settleAtInSeconds = $settleAtTime ? floor($settleAtTime / 1000) : null;
        $createdAtProviderTime = $transactionRequest['created_at'] ?? null;
        $createdAtProviderInSeconds = $createdAtProviderTime ? floor($createdAtProviderTime / 1000) : null;

        $providerName = GameList::where('product_code', $batchRequest['product_code'])->value('provider');
        $gameName = GameList::where('game_code', $transactionRequest['game_code'])->value('game_name');
        $playerId = User::where('user_name', $batchRequest['member_account'])->value('id');
        $playerAgentId = User::where('user_name', $batchRequest['member_account'])->value('agent_id');

        try {
            PlaceBet::create([
                'transaction_id' => $transactionRequest['id'] ?? '',
                'member_account' => $batchRequest['member_account'] ?? '',
                'player_id' => $playerId,
                'player_agent_id' => $playerAgentId,
                'product_code' => $batchRequest['product_code'] ?? 0,
                'provider_name' => $providerName ?? $batchRequest['product_code'] ?? null,
                'game_type' => $batchRequest['game_type'] ?? '',
                'operator_code' => $fullRequest->operator_code,
                'request_time' => $requestTimeInSeconds ? now()->setTimestamp($requestTimeInSeconds) : null,
                'sign' => $fullRequest->sign,
                'currency' => $fullRequest->currency,
                'action' => $transactionRequest['action'] ?? '',
                'amount' => $transactionRequest['amount'] ?? 0,
                'valid_bet_amount' => $transactionRequest['valid_bet_amount'] ?? null,
                'bet_amount' => $transactionRequest['bet_amount'] ?? null,
                'prize_amount' => $transactionRequest['prize_amount'] ?? null,
                'tip_amount' => $transactionRequest['tip_amount'] ?? null,
                'wager_code' => $transactionRequest['wager_code'] ?? null,
                'wager_status' => $transactionRequest['wager_status'] ?? null,
                'round_id' => $transactionRequest['round_id'] ?? null,
                'payload' => isset($transactionRequest['payload']) ? json_encode($transactionRequest['payload']) : null,
                'settle_at' => $settleAtInSeconds ? now()->setTimestamp($settleAtInSeconds) : null,
                'created_at_provider' => $createdAtProviderInSeconds ? now()->setTimestamp($createdAtProviderInSeconds) : null,
                'game_code' => $transactionRequest['game_code'] ?? null,
                'game_name' => $gameName ?? $transactionRequest['game_code'] ?? null,
                'channel_code' => $transactionRequest['channel_code'] ?? null,
                'status' => $status,
                'before_balance' => $beforeBalance,
                'balance' => $afterBalance,
                'error_message' => $errorMessage,
            ]);
        } catch (QueryException $e) {
            // MySQL: 23000, PostgreSQL: 23505 for unique constraint violation
            if (in_array($e->getCode(), ['23000', '23505'])) {
                Log::warning('Duplicate transaction detected when logging to PlaceBet, preventing re-insertion.', [
                    'transaction_id' => $transactionRequest['id'] ?? '',
                    'member_account' => $batchRequest['member_account'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            } else {
                throw $e; // Re-throw other database exceptions
            }
        }
    }
    
}