<?php

namespace App\Http\Controllers\Api\PoneWine;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\PoneWineBet;
use App\Models\PoneWineBetInfo;
use App\Models\PoneWinePlayerBet;
use Bavix\Wallet\External\Dto\Extra; 
use Bavix\Wallet\External\Dto\Option; 
use DateTimeImmutable; 
use DateTimeZone;     

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
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ClientSite: BalanceUpdateCallback validation failed', [
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_REQUEST_DATA',
                'message' => 'Invalid request data: ' . $e->getMessage(),
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

                if (!$user) {
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
                    'amountChanged' => $winLoseAmount
                ];

                // Refresh the user model to reflect the latest balance if needed for subsequent operations in the loop
                $user->refresh();
            }

            // Store game match data
            $gameMatchData = [
                'roomId' => $validated['roomId'],
                'matchId' => $validated['matchId'],
                'winNumber' => $validated['winNumber'],
                'players' => $validated['players']
            ];

            $gameMatch = PoneWineBet::storeGameMatchData($gameMatchData);
            Log::info('ClientSite: Game match data stored', [
                'match_id' => $gameMatch->match_id,
                'room_id' => $gameMatch->room_id,
                'win_number' => $gameMatch->win_number
            ]);
            

            DB::commit();

            Log::info('ClientSite: All balances updated successfully', ['match_id' => $validated['matchId']]);

            return response()->json([
                'status' => 'Request was successful.',
                'message' => 'Transaction Successful',
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ClientSite: Error processing balance update', [
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'payload' => $request->all(),
                'match_id' => $request->input('matchId'),
            ]);
            return response()->json([
                'status' => 'error', 'code' => 'INTERNAL_SERVER_ERROR', 'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
