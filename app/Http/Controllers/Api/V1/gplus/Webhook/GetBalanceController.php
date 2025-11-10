<?php

namespace App\Http\Controllers\Api\V1\gplus\Webhook;

use App\Enums\SeamlessWalletCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class GetBalanceController extends Controller
{
    public function getBalance(Request $request)
    {
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
        $isValidCurrency = in_array($request->currency, $allowedCurrencies, true);

        $results = [];
        $specialCurrencies = ['IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'];
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
            if ($user && is_numeric($user->balance)) {
                $normalizedBalance = $this->toScaledString($user->balance);
                $balance = $this->formatBalanceForResponse($normalizedBalance, $request->currency);
                $results[] = [
                    'member_account' => $req['member_account'],
                    'product_code' => $req['product_code'],
                    'balance' => $balance,
                    'code' => \App\Enums\SeamlessWalletCode::Success->value,
                    'message' => 'Success',
                ];
            } else {
                $results[] = [
                    'member_account' => $req['member_account'],
                    'product_code' => $req['product_code'],
                    'balance' => (float) 0.00,
                    'code' => \App\Enums\SeamlessWalletCode::MemberNotExist->value,
                    'message' => 'Member not found',
                ];
            }
        }

        return ApiResponseService::success($results);
    }

    private function formatBalanceForResponse(string $balance, string $currency): string
    {
        $divider = $this->getCurrencyValue($currency);
        $scale = in_array($currency, ['IDR2', 'KRW2', 'MMK2', 'VND2', 'LAK2', 'KHR2'], true) ? 4 : 2;

        return bcdiv($balance, (string) $divider, $scale);
    }

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

    private function toScaledString(string|int|float $value): string
    {
        return bcadd((string) $value, '0', 4);
    }
}
