<?php

namespace App\Http\Controllers\Api\V1\gplus\Webhook;

use App\Enums\SeamlessWalletCode;
use App\Http\Controllers\Controller;
use App\Models\PlaceBet;
use App\Models\PushBet;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;


class PushBetDataController extends Controller
{
    public function pushBetData(Request $request)
    {
        // Log::info('Push Bet Data API Request', ['request' => $request->all()]);

        try {
            $request->validate([
                'operator_code' => 'required|string',
                'wagers' => 'required|array',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Push Bet Data API Validation Failed', ['errors' => $e->errors()]);

            return ApiResponseService::error(
                SeamlessWalletCode::InternalServerError, // Or a more specific validation error code
                'Validation failed',
                $e->errors()
            );
        }

        $secretKey = Config::get('seamless_key.secret_key');
        $expectedSign = md5(
            $request->operator_code.
            $request->request_time.
            'pushbetdata'.
            $secretKey
        );

        if (!empty($request->sign)) {
            if (strtolower($request->sign) !== strtolower($expectedSign)) {
                Log::warning('Push Bet Data Invalid Signature', ['provided' => $request->sign, 'expected' => $expectedSign]);
                return response()->json([
                    'code' => SeamlessWalletCode::InvalidSignature->value,
                    'message' => 'Invalid signature',
                ]);
            }
        }

        foreach ($request->wagers as $tx) {
            $memberAccount = $tx['member_account'] ?? null;
            $user = User::where('user_name', $memberAccount)->first();

            if (! $user) {
                Log::warning('Member not found for pushBetData', ['member_account' => $memberAccount, 'transaction' => $tx]);

                
                return response()->json([
                    'code' => SeamlessWalletCode::MemberNotExist->value,
                    'message' => 'Member not found',
                ]);
            }

            // Use wager_code as the unique identifier for upsert
            $wagerCode = $tx['wager_code'] ?? null;
            if (!$wagerCode) {
                Log::warning('Transaction missing wager_code in pushBetData', ['tx' => $tx]);
                continue; // Skip this specific wager if it lacks a wager_code
            }

            // Convert timestamps from milliseconds to seconds if they are in milliseconds
            $requestTimeInSeconds = null;
            if (isset($request->request_time) && is_numeric($request->request_time)) {
                $requestTimeInSeconds = floor($request->request_time / 1000);
            }
            
            $settledAtInSeconds = (isset($tx['settled_at']) && $tx['settled_at']) ? floor($tx['settled_at'] / 1000) : null;
            $createdAtProviderInSeconds = (isset($tx['created_at']) && $tx['created_at']) ? floor($tx['created_at'] / 1000) : null;

            $pushBet = PushBet::where('wager_code', $wagerCode)->first();

            // Convert string values to appropriate types for database
            $gameCode = $tx['game_code'] ?? null;
            $productCode = is_numeric($tx['product_code'] ?? '') ? (int)($tx['product_code']) : null;
            $betAmount = is_numeric($tx['bet_amount'] ?? '') ? (float)($tx['bet_amount']) : 0;
            $validBetAmount = is_numeric($tx['valid_bet_amount'] ?? '') ? (float)($tx['valid_bet_amount']) : 0;
            $prizeAmount = is_numeric($tx['prize_amount'] ?? '') ? (float)($tx['prize_amount']) : 0;
            $tipAmount = is_numeric($tx['tip_amount'] ?? '') ? (float)($tx['tip_amount']) : 0;

            if ($pushBet) {
                // Update existing record
                $pushBet->update([
                    'member_account'      => $tx['member_account'] ?? $pushBet->member_account,
                    'currency'            => $tx['currency'] ?? $pushBet->currency,
                    'product_code'        => $productCode ?? $pushBet->product_code,
                    'game_code'           => $gameCode ?? $pushBet->game_code,
                    'game_type'           => $tx['game_type'] ?? $pushBet->game_type,
                    'wager_code'          => $tx['wager_code'] ?? $pushBet->wager_code,
                    'wager_type'          => $tx['wager_type'] ?? $pushBet->wager_type,
                    'wager_status'        => $tx['wager_status'] ?? $pushBet->wager_status,
                    'bet_amount'          => $betAmount ?? $pushBet->bet_amount,
                    'valid_bet_amount'    => $validBetAmount ?? $pushBet->valid_bet_amount,
                    'prize_amount'        => $prizeAmount ?? $pushBet->prize_amount,
                    'tip_amount'          => $tipAmount ?? $pushBet->tip_amount,
                    'created_at_provider' => (isset($tx['created_at']) && is_numeric($tx['created_at'])) ? now()->setTimestamp($tx['created_at']) : $pushBet->created_at_provider,
                    'settled_at'          => (isset($tx['settled_at']) && is_numeric($tx['settled_at'])) ? now()->setTimestamp($tx['settled_at']) : $pushBet->settled_at,
                    'meta'                => json_encode($tx),
                ]);
            } else {
                // Insert new record
                PushBet::create([
                    'member_account'      => $tx['member_account'] ?? '',
                    'currency'            => $tx['currency'] ?? '',
                    'product_code'        => $productCode ?? 0,
                    'game_code'           => $gameCode ?? null,
                    'game_type'           => $tx['game_type'] ?? '',
                    'wager_code'          => $tx['wager_code'] ?? '',
                    'wager_type'          => $tx['wager_type'] ?? '',
                    'wager_status'        => $tx['wager_status'] ?? '',
                    'bet_amount'          => $betAmount ?? 0,
                    'valid_bet_amount'    => $validBetAmount ?? 0,
                    'prize_amount'        => $prizeAmount ?? 0,
                    'tip_amount'          => $tipAmount ?? 0,
                    'created_at_provider' => (isset($tx['created_at']) && is_numeric($tx['created_at'])) ? now()->setTimestamp($tx['created_at']) : null,
                    'settled_at'          => (isset($tx['settled_at']) && is_numeric($tx['settled_at'])) ? now()->setTimestamp($tx['settled_at']) : null,
                    'meta'                => json_encode($tx),
                ]);
            }
        }

        return response()->json([
            'code' => SeamlessWalletCode::Success->value,
            'message' => '',
        ]);
    }
}

