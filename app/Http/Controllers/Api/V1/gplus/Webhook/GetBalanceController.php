<?php

namespace App\Http\Controllers\Api\V1\gplus\Webhook;

use App\Enums\SeamlessWalletCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;


class GetBalanceController extends Controller
{
    public function getBalance(Request $request)
    {
        Log::debug('=== DEBUGGING BALANCE ISSUE - GetBalanceController ===');
        Log::debug('GetBalanceController: Incoming Request', ['request' => $request->all()]);
        
        // Validate request
        $request->validate([
            'batch_requests' => 'required|array',
            'operator_code' => 'required|string',
            'currency' => 'required|string',
            'sign' => 'required|string',
            'request_time' => 'required|integer',
        ]);

        // Signature check
        $secretKey = Config::get('seamless_key.secret_key');
        $expectedSign = md5(
            $request->operator_code.
            $request->request_time.
            'getbalance'.
            $secretKey
        );
        $isValidSign = strtolower($request->sign) === strtolower($expectedSign);

        // Allowed currencies
        // $allowedCurrencies = ['MMK', 'VND', 'INR', 'MYR', 'AOA', 'EUR', 'IDR', 'PHP', 'THB', 'JPY', 'COP', 'IRR', 'CHF', 'USD', 'MXN', 'ETB', 'CAD', 'BRL', 'NGN', 'KES', 'KRW', 'TND', 'LBP', 'BDT', 'CZK', 'IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];

        $allowedCurrencies = ['MMK', 'IDR', 'IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];
        $isValidCurrency = in_array($request->currency, $allowedCurrencies);

        $results = [];
        $specialCurrencies = ['IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];
        
        Log::debug('GetBalanceController: Processing batch requests', [
            'currency' => $request->currency,
            'isValidSign' => $isValidSign,
            'isValidCurrency' => $isValidCurrency,
            'specialCurrencies' => $specialCurrencies,
            'batch_count' => count($request->batch_requests)
        ]);
        
        foreach ($request->batch_requests as $req) {
            if (! $isValidSign) {
                $results[] = [
                    'member_account' => $req['member_account'],
                    'product_code' => $req['product_code'],
                    'balance' => (float) 0.00,
                    'code' => \App\Enums\SeamlessWalletCode::InvalidSignature->value,
                    'message' => 'Incorrect Signature',
                ];

                continue;
            }

            if (! $isValidCurrency) {
                $results[] = [
                    'member_account' => $req['member_account'],
                    'product_code' => $req['product_code'],
                    'balance' => (float) 0.00,
                    'code' => \App\Enums\SeamlessWalletCode::InternalServerError->value,
                    'message' => 'Invalid Currency',
                ];

                continue;
            }

            $user = User::where('user_name', $req['member_account'])->first();
            
            Log::debug('GetBalanceController: Processing member', [
                'member_account' => $req['member_account'],
                'product_code' => $req['product_code'],
                'user_found' => $user ? true : false,
                'user_id' => $user ? $user->id : null,
                'raw_balance' => $user ? $user->balance : null,
                'balance_type' => $user ? gettype($user->balance) : null
            ]);
            
            if ($user && $user->balance) {
                $balance = $user->balance;
                $isSpecialCurrency = in_array($request->currency, $specialCurrencies);
                
                Log::debug('GetBalanceController: Balance calculation', [
                    'member_account' => $req['member_account'],
                    'currency' => $request->currency,
                    'isSpecialCurrency' => $isSpecialCurrency,
                    'raw_balance' => $balance,
                    'conversion_divisor' => $isSpecialCurrency ? 1000 : 1,
                    'decimal_places' => $isSpecialCurrency ? 4 : 2
                ]);
                
                if ($isSpecialCurrency) {
                    $balance = $balance / 1000; // Apply 1:1000 conversion here (matching working version)
                    $balance = round($balance, 4);
                } else {
                    $balance = round($balance, 2);
                }
                
                // Ensure we always return a float with proper decimal places
                $balance = (float) $balance;
                
                Log::debug('GetBalanceController: Final balance for response', [
                    'member_account' => $req['member_account'],
                    'final_balance' => $balance,
                    'final_balance_type' => gettype($balance),
                    'json_encoded' => json_encode($balance)
                ]);
                
                $results[] = [
                    'member_account' => $req['member_account'],
                    'product_code' => $req['product_code'],
                    'balance' => (float) $balance,
                    'code' => \App\Enums\SeamlessWalletCode::Success->value,
                    'message' => 'Success',
                ];
            } else {
                $results[] = [
                    'member_account' => $req['member_account'],
                    'product_code' => $req['product_code'],
                    'balance' => 0,
                    'code' => \App\Enums\SeamlessWalletCode::MemberNotExist->value,
                    'message' => 'Member not found',
                ];
            }
        }

        Log::debug('GetBalanceController: Final response', [
            'results' => $results,
            'results_count' => count($results)
        ]);
        
        return ApiResponseService::success($results);
    }

    /**
     * Gets the currency conversion value.
     */
    private function getCurrencyValue(string $currency): int
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
}
