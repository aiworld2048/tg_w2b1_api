<?php

namespace App\Http\Controllers\Api\V1\Shan;

use App\Enums\SeamlessWalletCode;
use App\Http\Controllers\Controller;
use App\Models\GameList;
use App\Models\User;
use App\Services\ApiResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShanLaunchGameController extends Controller
{
    private const LANGUAGE_CODE = 0; // Keeping as 0 as per your provided code

    private const PLATFORM_WEB = 'WEB';

    private const PLATFORM_DESKTOP = 'DESKTOP';

    private const PLATFORM_MOBILE = 'MOBILE';

    // Removed generateGameToken and verifyGameToken as they are no longer needed
    // for the 'password' field based on provider's clarification.
    // However, if your application uses them for other internal purposes, keep them.

    /**
     * Handles the game launch request by calling the provider's API.
     * This method validates the incoming request, authenticates the user,
     * and calls the provider site to get the launch game URL.
     *
     * @param  Request  $request  The incoming HTTP request containing game launch details.
     * @return \Illuminate\Http\JsonResponse
     */
    public function launchGame(Request $request)
    {
        Log::info('Launch Game API Request', ['request' => $request->all()]);

        $user = Auth::user();
        if (! $user) {
            Log::warning('Unauthenticated user attempting game launch.');

            return ApiResponseService::error(
                SeamlessWalletCode::MemberNotExist,
                'Authentication required. Please log in.'
            );
        }

        try {
            $validatedData = $request->validate([
                'product_code' => 'required|integer',
                'game_type' => 'required|string',
            ]);

            $agentCode = config('shan_key.agent_code');
            $callBackUrl = config('shan_key.callback_url');
            $secret_key = config('shan_key.secret_key');
            $balance = $user->balanceFloat;
            $memberAccount = $user->user_name;

            // Generate request time in GMT+8 (Asia/Shanghai) as integer timestamp
            $requestTime = now('Asia/Shanghai')->timestamp;

            // Generate signature following the pattern: md5(request_time + secret_key + 'launchgame' + agent_code)
            $sign = md5($requestTime.$secret_key.'launchgame'.$agentCode);

            // Prepare payload for provider API
            $payload = [
                'agent_code' => $agentCode,
                'product_code' => $validatedData['product_code'],
                'game_type' => $validatedData['game_type'],
                'member_account' => $memberAccount,
                'balance' => $balance,
                'callback_url' => $callBackUrl,
                'request_time' => $requestTime,
                'sign' => $sign,
            ];

            if ($request->has('nickname')) {
                $payload['nickname'] = $request->input('nickname');
            }

            Log::info('Payload', ['payload' => $payload]);

            // Call provider API to get launch game URL
            $providerApiUrl = config('shan_key.api_url').'/api/client/launch-game';

            Log::info('Calling provider API', [
                'provider_url' => $providerApiUrl,
                'payload' => $payload,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($providerApiUrl, $payload);

            $responseData = $response->json();

            if (! $response->successful()) {
                Log::error('Provider API call failed', [
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                return response()->json([
                    'code' => $response->status(),
                    'message' => $responseData['message'] ?? 'Provider API call failed',
                ], $response->status());
            }

            Log::info('Provider API call successful', [
                'response' => $responseData,
            ]);

            // Return the launch game URL from provider
            return response()->json([
                'code' => $responseData['code'] ?? 200,
                'message' => $responseData['message'] ?? 'Game launched successfully',
                'url' => $responseData['url'],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Launch Game API Validation Failed', ['errors' => $e->errors()]);

            return ApiResponseService::error(
                SeamlessWalletCode::InternalServerError,
                'Validation failed',
                $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('Game launch failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Game launch failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
