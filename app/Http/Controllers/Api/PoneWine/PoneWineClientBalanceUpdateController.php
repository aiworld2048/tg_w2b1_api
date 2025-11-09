<?php

namespace App\Http\Controllers\Api\PoneWine;

use App\Http\Controllers\Controller;
use App\Models\PoneWineBet;
use App\Models\PoneWineBetInfo;
use App\Models\PoneWinePlayerBet;
use App\Models\PoneWineTransaction;
use App\Models\User;
use Bavix\Wallet\External\Dto\Extra;
use Bavix\Wallet\External\Dto\Option;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoneWineClientBalanceUpdateController extends Controller
{
    public function PoneWineClientReport(Request $request)
    {
        Log::info('PoneWine ClientSite: PoneWineClientReport received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        try {
            $validated = $request->validate([
                'roomId' => 'required|integer',
                'matchId' => 'required|string|max:255',
                'winNumber' => 'required|integer',
                'players' => 'required|array',
                'players.*.player_id' => 'required|string|max:255',
                'players.*.balance' => 'required|numeric|min:0',
                'players.*.winLoseAmount' => 'required|numeric',
                'players.*.betInfos' => 'required|array',
                'players.*.betInfos.*.betNumber' => 'required|integer',
                'players.*.betInfos.*.betAmount' => 'required|numeric|min:0',
                'players.*.client_agent_name' => 'nullable|string',
                'players.*.client_agent_id' => 'nullable|string',
                'players.*.pone_wine_player_bet' => 'nullable|array',
                'players.*.pone_wine_bet_infos' => 'nullable|array',
                // Provider database model information
                'pone_wine_bet' => 'nullable|array',
                'pone_wine_player_bets' => 'nullable|array',
                'pone_wine_bet_infos' => 'nullable|array',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ClientSite: BalanceUpdateCallback validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_REQUEST_DATA',
                'message' => 'Invalid request data: '.$e->getMessage(),
            ], 400);
        }

        // No signature validation needed - provider doesn't send signature

        try {
            DB::beginTransaction();

            // Idempotency Check (CRITICAL) - Check if match already exists
            if (PoneWineBet::where('match_id', $validated['matchId'])->exists()) {
                DB::commit();
                Log::info('ClientSite: Duplicate match_id received, skipping processing.', ['match_id' => $validated['matchId']]);

                return response()->json(['status' => 'success', 'code' => 'ALREADY_PROCESSED', 'message' => 'Match already processed.'], 200);
            }

            $responseData = [];

            foreach ($validated['players'] as $playerData) {
                $user = User::where('user_name', $playerData['player_id'])->first();

                if (! $user) {
                    Log::error('ClientSite: Player not found for balance update. Rolling back transaction.', [
                        'player_id' => $playerData['player_id'], 'match_id' => $validated['matchId'],
                    ]);
                    throw new \RuntimeException("Player {$playerData['player_id']} not found on client site.");
                }

                $currentBalance = $user->wallet->balanceFloat; // Get current balance
                $winLoseAmount = $playerData['winLoseAmount']; // Amount to add/subtract from provider
                $providerExpectedBalance = $playerData['balance']; // Provider's expected final balance

                Log::info('ClientSite: Processing player balance update', [
                    'player_id' => $user->user_name,
                    'current_balance' => $currentBalance,
                    'provider_expected_balance' => $providerExpectedBalance,
                    'win_lose_amount' => $winLoseAmount,
                    'match_id' => $validated['matchId'],
                ]);

                $meta = [
                    'match_id' => $validated['matchId'],
                    'room_id' => $validated['roomId'],
                    'win_number' => $validated['winNumber'],
                    'provider_expected_balance' => $providerExpectedBalance,
                    'client_old_balance' => $currentBalance,
                    'description' => 'Pone Wine game settlement from provider',
                ];

                if ($winLoseAmount > 0) {
                    // Player won or received funds
                    $user->depositFloat($winLoseAmount, $meta);
                    Log::info('ClientSite: Deposited to player wallet', [
                        'player_id' => $user->user_name, 'amount' => $winLoseAmount,
                        'new_balance' => $user->wallet->balanceFloat, 'match_id' => $validated['matchId'],
                    ]);
                } elseif ($winLoseAmount < 0) {
                    // Player lost or paid funds
                    $user->forceWithdrawFloat(abs($winLoseAmount), $meta);
                    Log::info('ClientSite: Withdrew from player wallet', [
                        'player_id' => $user->user_name, 'amount' => abs($winLoseAmount),
                        'new_balance' => $user->wallet->balanceFloat, 'match_id' => $validated['matchId'],
                    ]);
                } else {
                    // Balance is the same, no action needed
                    Log::info('ClientSite: Player balance unchanged', [
                        'player_id' => $user->user_name, 'balance' => $currentBalance, 'match_id' => $validated['matchId'],
                    ]);
                }

                // Add to response data
                $responseData[] = [
                    'playerId' => $user->user_name,
                    'balance' => number_format($user->wallet->balanceFloat, 2, '.', ''),
                    'amountChanged' => $winLoseAmount,
                ];

                // Refresh the user model to reflect the latest balance if needed for subsequent operations in the loop
                $user->refresh();
            }

            // Store complete transaction data in single table
            $this->storeTransactionData($validated, $responseData);

            DB::commit();

            Log::info('ClientSite: All balances updated successfully', ['match_id' => $validated['matchId']]);

            return response()->json([
                'status' => 'Request was successful.',
                'message' => 'Transaction Successful',
                'data' => $responseData,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ClientSite: Error processing balance update', [
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'payload' => $request->all(),
                'match_id' => $request->input('matchId'),
            ]);

            return response()->json([
                'status' => 'error', 'code' => 'INTERNAL_SERVER_ERROR', 'message' => 'Internal server error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store transaction data in single comprehensive table
     */
    private function storeTransactionData(array $validated, array $responseData): void
    {
        try {
            $gameData = [
                'roomId' => $validated['roomId'],
                'matchId' => $validated['matchId'],
                'winNumber' => $validated['winNumber'],
            ];

            // Store each player's transaction
            foreach ($validated['players'] as $index => $playerData) {
                $user = User::where('user_name', $playerData['player_id'])->first();
                $player_agent_id = $user->agent_id;
                $player_agent_name = $user->agent->user_name;

                if (! $user) {
                    Log::warning('ClientSite: User not found for transaction storage', [
                        'player_id' => $playerData['player_id'],
                    ]);

                    continue;
                }

                // Get balance information from our response data
                $balanceInfo = $responseData[$index] ?? null;
                $balanceBefore = $user->wallet->balanceFloat - ($playerData['winLoseAmount'] ?? 0);
                $balanceAfter = $balanceInfo['balance'] ?? $user->wallet->balanceFloat;

                // Store each bet info as separate transaction record
                foreach ($playerData['betInfos'] as $betInfo) {
                    try {
                        // Check if this specific transaction already exists
                        $existingTransaction = PoneWineTransaction::where('match_id', $validated['matchId'])
                            ->where('user_id', $user->id)
                            ->where('bet_number', $betInfo['betNumber'])
                            ->first();

                        if ($existingTransaction) {
                            Log::info('ClientSite: Transaction already exists, skipping', [
                                'match_id' => $validated['matchId'],
                                'user_id' => $user->id,
                                'bet_number' => $betInfo['betNumber'],
                            ]);

                            continue;
                        }

                        PoneWineTransaction::storeFromProviderPayload(
                            $gameData,
                            $playerData,
                            $betInfo,
                            $user,
                            $balanceBefore,
                            $balanceAfter,
                            $player_agent_id,
                            $player_agent_name
                        );

                        Log::info('ClientSite: Transaction stored', [
                            'match_id' => $validated['matchId'],
                            'user_name' => $user->user_name,
                            'bet_number' => $betInfo['betNumber'],
                            'bet_amount' => $betInfo['betAmount'],
                        ]);

                    } catch (\Exception $e) {
                        Log::error('ClientSite: Failed to store individual transaction', [
                            'error' => $e->getMessage(),
                            'match_id' => $validated['matchId'],
                            'user_name' => $user->user_name,
                            'bet_number' => $betInfo['betNumber'] ?? 'unknown',
                        ]);
                    }
                }
            }

            Log::info('ClientSite: Transaction data storage completed', [
                'match_id' => $validated['matchId'],
                'players_count' => count($validated['players']),
            ]);

        } catch (\Exception $e) {
            Log::error('ClientSite: Failed to store transaction data', [
                'error' => $e->getMessage(),
                'match_id' => $validated['matchId'],
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
