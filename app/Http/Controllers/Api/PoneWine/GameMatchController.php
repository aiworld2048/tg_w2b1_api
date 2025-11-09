<?php

namespace App\Http\Controllers\Api\PoneWine;

use App\Http\Controllers\Controller;
use App\Models\PoneWineBet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameMatchController extends Controller
{
    /**
     * Store game match data
     */
    public function storeGameMatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'roomId' => 'required|integer',
                'matchId' => 'required|string|max:255',
                'winNumber' => 'required|integer',
                'players' => 'required|array',
                'players.*.playerId' => 'required|string|max:255',
                'players.*.winLoseAmount' => 'required|numeric',
                'players.*.betInfos' => 'required|array',
                'players.*.betInfos.*.betNumber' => 'required|integer',
                'players.*.betInfos.*.betAmount' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            // Check if match already exists
            if (PoneWineBet::where('match_id', $validated['matchId'])->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Match already exists',
                ], 400);
            }

            // Store the game match data
            $gameMatch = PoneWineBet::storeGameMatchData($validated);

            DB::commit();

            Log::info('Game match data stored successfully', [
                'match_id' => $gameMatch->match_id,
                'room_id' => $gameMatch->room_id,
                'win_number' => $gameMatch->win_number,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Game match data stored successfully',
                'data' => [
                    'id' => $gameMatch->id,
                    'match_id' => $gameMatch->match_id,
                    'room_id' => $gameMatch->room_id,
                    'win_number' => $gameMatch->win_number,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing game match data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get game match data with relationships
     */
    public function getGameMatch($matchId)
    {
        try {
            $gameMatch = PoneWineBet::with(['players.poneWineBetInfos'])
                ->where('match_id', $matchId)
                ->first();

            if (! $gameMatch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Game match not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $gameMatch,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving game match data', [
                'error' => $e->getMessage(),
                'match_id' => $matchId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
