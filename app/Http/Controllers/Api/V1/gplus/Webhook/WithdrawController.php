<?php

namespace App\Http\Controllers\Api\V1\gplus\Webhook;

use App\Enums\SeamlessWalletCode;
use App\Enums\TransactionName;
use App\Http\Controllers\Controller;
use App\Models\CustomTransaction;
use App\Models\GameList;
use App\Models\PlaceBet;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\WalletService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class WithdrawController extends Controller
{
    protected WalletService $walletService;

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
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Handle incoming withdraw/bet requests.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw(Request $request)
    {
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

        // If you want to handle the case when the balance is sufficient, add your logic here.
        // For now, there is no else/other case.

        // Process all transactions in the batch
        $results = $this->processWithdrawTransactions($request);

        // Log the overall batch request and its final outcome
        // This provides an audit trail for the entire webhook call
        \App\Models\TransactionLog::create([ // Use full namespace to avoid alias conflict if any
            'type' => 'withdraw',
            'batch_request' => $request->all(),
            'response_data' => $results,
            'status' => collect($results)->every(fn ($r) => $r['code'] === SeamlessWalletCode::Success->value) ? 'success' : 'partial_success_or_failure',
        ]);

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
            'withdraw'.
            $secretKey
        );
        $isValidSign = strtolower($request->sign) === strtolower($expectedSign);
        $isValidCurrency = in_array($request->currency, $this->allowedCurrencies, true);

        $responseData = [];

        foreach ($request->batch_requests as $batchRequest) {
            Log::info('Withdraw Batch Request', ['batchRequest' => $batchRequest]);

            $memberAccount = $batchRequest['member_account'] ?? null;
            $productCode = $batchRequest['product_code'] ?? null;

            if (! $isValidSign) {
                Log::warning('Invalid signature for batch', ['member_account' => $memberAccount, 'provided' => $request->sign, 'expected' => $expectedSign]);
                $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, '0.0000', SeamlessWalletCode::InvalidSignature, 'Invalid signature', $request->currency);

                continue;
            }

            if (! $isValidCurrency) {
                Log::warning('Invalid currency for batch', ['member_account' => $memberAccount, 'currency' => $request->currency]);
                $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, '0.0000', SeamlessWalletCode::InternalServerError, 'Invalid Currency', $request->currency);

                continue;
            }

            try {
                $user = User::where('user_name', $memberAccount)->first();

                if (! $user) {
                    Log::warning('Member not found for withdraw/bet request', ['member_account' => $memberAccount]);
                    $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, '0.0000', SeamlessWalletCode::MemberNotExist, 'Member not found', $request->currency);

                    continue;
                }

                if (! is_numeric($user->balance)) {
                    Log::warning('Invalid balance for member during withdraw/bet request', ['member_account' => $memberAccount, 'balance' => $user->balance]);
                    $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, '0.0000', SeamlessWalletCode::MemberNotExist, 'Invalid user balance', $request->currency);

                    continue;
                }

                $currentBalance = $this->toScaledString($user->balance);

                foreach ($batchRequest['transactions'] ?? [] as $tx) {
                    $transactionId = $tx['id'] ?? null;
                    $action = strtoupper($tx['action'] ?? '');
                    $amount = floatval($tx['amount'] ?? 0);
                    $wagerCode = $tx['wager_code'] ?? $tx['round_id'] ?? null;
                    $gameCode = $tx['game_code'] ?? null;

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
                        $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Missing game_type', (float) $currentBalance, (float) $currentBalance);

                        continue;
                    }

                    if (! $transactionId || empty($action)) {
                        Log::warning('Missing crucial data in transaction for withdraw/bet', ['tx' => $tx]);
                        $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Missing transaction data (id or action)', (float) $currentBalance, (float) $currentBalance);
                        $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, $currentBalance, SeamlessWalletCode::InternalServerError, 'Missing transaction data (id or action)', $request->currency);

                        continue;
                    }

                    $convertedAmount = $this->convertAmount(abs($amount), $request->currency);

                    $meta = [
                        'seamless_transaction_id' => $transactionId,
                        'action_type' => $action,
                        'product_code' => $productCode,
                        'wager_code' => $wagerCode,
                        'round_id' => $tx['round_id'] ?? null,
                        'game_code' => $gameCode,
                        'game_type' => $transactionGameType,
                        'channel_code' => $tx['channel_code'] ?? null,
                        'raw_payload' => $tx,
                    ];

                    $isDuplicate = PlaceBet::where('transaction_id', $transactionId)->exists()
                        || CustomTransaction::whereJsonContains('meta->seamless_transaction_id', $transactionId)->exists();

                    if ($isDuplicate) {
                        Log::warning('Duplicate transaction ID detected for withdraw/bet', ['tx_id' => $transactionId, 'member_account' => $memberAccount, 'action' => $action]);
                        $this->logPlaceBet($batchRequest, $request, $tx, 'duplicate', $request->request_time, 'Duplicate transaction', (float) $currentBalance, (float) $currentBalance);
                        $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, $currentBalance, SeamlessWalletCode::DuplicateTransaction, 'Duplicate transaction', $request->currency);

                        continue;
                    }

                    if (! in_array($action, $this->debitActions, true)) {
                        Log::warning('Unsupported action type received on withdraw endpoint', ['transaction_id' => $transactionId, 'action' => $action]);
                        $this->logPlaceBet($batchRequest, $request, $tx, 'failed', $request->request_time, 'Unsupported action type for this endpoint: '.$action, (float) $currentBalance, (float) $currentBalance);
                        $responseData[] = $this->buildErrorResponse($memberAccount, $productCode, $currentBalance, SeamlessWalletCode::InternalServerError, 'Unsupported action type: '.$action, $request->currency);

                        continue;
                    }

                    if (bccomp($convertedAmount, '0', 4) <= 0) {
                        Log::info('WithdrawController: Processing debit action with zero/negative amount', [
                            'member_account' => $memberAccount,
                            'action' => $action,
                            'amount' => $amount,
                        ]);

                        $message = 'Processed with zero amount, no balance change.';
                        $this->logPlaceBet(
                            $batchRequest,
                            $request,
                            $tx,
                            'info',
                            $request->request_time,
                            $message,
                            (float) $currentBalance,
                            (float) $currentBalance
                        );

                        $formattedBalance = $this->formatBalanceForResponse($currentBalance, $request->currency);

                        $responseData[] = [
                            'member_account' => $memberAccount,
                            'product_code' => (int) $productCode,
                            'before_balance' => $formattedBalance,
                            'balance' => $formattedBalance,
                            'code' => SeamlessWalletCode::Success->value,
                            'message' => $message,
                        ];

                        continue;
                    }

                    try {
                        $user->refresh();
                        $beforeTransactionBalance = $this->toScaledString($user->balance);

                        if (bccomp($beforeTransactionBalance, $convertedAmount, 4) < 0) {
                            $transactionCode = SeamlessWalletCode::InsufficientBalance->value;
                            $transactionMessage = 'Insufficient balance';

                            $this->logPlaceBet(
                                $batchRequest,
                                $request,
                                $tx,
                                'failed',
                                $request->request_time,
                                $transactionMessage,
                                (float) $beforeTransactionBalance,
                                (float) $beforeTransactionBalance
                            );

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

                        $updatedUser = $this->walletService->withdraw($user, $convertedAmount, TransactionName::Withdraw, $meta);
                        $afterTransactionBalance = $this->toScaledString($updatedUser->balance);

                        $transactionCode = SeamlessWalletCode::Success->value;
                        $transactionMessage = 'Transaction processed successfully';

                        $this->logPlaceBet(
                            $batchRequest,
                            $request,
                            $tx,
                            'completed',
                            $request->request_time,
                            $transactionMessage,
                            (float) $beforeTransactionBalance,
                            (float) $afterTransactionBalance
                        );

                        $currentBalance = $afterTransactionBalance;
                        $user = $updatedUser;
                    } catch (RuntimeException|InvalidArgumentException $e) {
                        $transactionCode = SeamlessWalletCode::InsufficientBalance->value;
                        $transactionMessage = $e->getMessage();

                        Log::warning('Insufficient balance for withdraw/bet', [
                            'transaction_id' => $transactionId,
                            'member_account' => $memberAccount,
                            'amount' => $amount,
                            'error' => $e->getMessage(),
                        ]);

                        $this->logPlaceBet(
                            $batchRequest,
                            $request,
                            $tx,
                            'failed',
                            $request->request_time,
                            $transactionMessage,
                            isset($beforeTransactionBalance) ? (float) $beforeTransactionBalance : (float) $currentBalance,
                            isset($beforeTransactionBalance) ? (float) $beforeTransactionBalance : (float) $currentBalance
                        );

                        $currentBalance = $beforeTransactionBalance ?? $currentBalance;
                    } catch (Exception $e) {
                        $transactionCode = SeamlessWalletCode::InternalServerError->value;
                        $transactionMessage = 'Failed to process transaction: '.$e->getMessage();

                        Log::error('Error processing withdraw/bet transaction', [
                            'transaction_id' => $transactionId,
                            'action' => $action,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        $this->logPlaceBet(
                            $batchRequest,
                            $request,
                            $tx,
                            'failed',
                            $request->request_time,
                            $transactionMessage,
                            isset($beforeTransactionBalance) ? (float) $beforeTransactionBalance : (float) $currentBalance,
                            (float) $currentBalance
                        );
                    }

                    $responseData[] = [
                        'member_account' => $memberAccount,
                        'product_code' => (int) $productCode,
                        'before_balance' => $this->formatBalanceForResponse($beforeTransactionBalance ?? $currentBalance, $request->currency),
                        'balance' => $this->formatBalanceForResponse($currentBalance, $request->currency),
                        'code' => $transactionCode,
                        'message' => $transactionMessage,
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('Batch processing exception for member in WithdrawController', [
                    'member_account' => $memberAccount,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $responseData[] = $this->buildErrorResponse(
                    $memberAccount,
                    $productCode,
                    '0.0000',
                    SeamlessWalletCode::InternalServerError,
                    'An unexpected error occurred during batch processing: '.$e->getMessage(),
                    $request->currency
                );
            }
        }

        return $responseData;
    }

    /**
     * Helper to build a consistent error response.
     */
    private function buildErrorResponse(string $memberAccount, string|int|null $productCode, string $balance, SeamlessWalletCode $code, string $message, string $currency): array
    {
        return [
            'member_account' => $memberAccount,
            'product_code' => (int) $productCode, // Ensure it's an int
            'before_balance' => $this->formatBalanceForResponse($balance, $currency),
            'balance' => $this->formatBalanceForResponse($balance, $currency),
            'code' => $code->value,
            'message' => $message,
        ];
    }

    private function convertAmount(float $amount, string $currency): string
    {
        $multiplier = $this->getCurrencyValue($currency);
        $scaled = number_format($amount * $multiplier, 4, '.', '');

        return $this->toScaledString($scaled);
    }

    private function formatBalanceForResponse(string $balance, string $currency): float
    {
        $divider = $this->getCurrencyValue($currency);
        $scale = in_array($currency, $this->specialCurrencies, true) ? 4 : 2;

        $normalized = bcdiv($this->toScaledString($balance), (string) $divider, $scale);

        return (float) $normalized;
    }

    private function toScaledString(string|int|float $value): string
    {
        return bcadd((string) $value, '0', 4);
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
            'MMK2' => 1000,
            'VND2' => 1000,
            'LAK2' => 10,
            'KHR2' => 100,
            default => 1,
        };
    }

    /**
     * Logs the transaction attempt in the place_bets table.
     *
     * @param  array  $batchRequest  The current batch request being processed.
     * @param  Request  $fullRequest  The full incoming HTTP request.
     * @param  array  $transactionRequest  The individual transaction details from the batch.
     * @param  string  $status  The status of the transaction ('completed', 'failed', 'duplicate', 'info', 'loss').
     * @param  int|null  $requestTime  The original request_time from the full request (milliseconds).
     * @param  string|null  $errorMessage  Optional error message.
     * @param  float|null  $beforeBalance  Optional balance before the transaction.
     * @param  float|null  $afterBalance  Optional balance after the transaction.
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
