<?php

namespace App\Services;

use App\Enums\SeamlessWalletCode;
use Illuminate\Http\JsonResponse; // ✅ correct

class ApiResponseService
{
    

    public static function success(mixed $data = null, string $message = 'Success')
    {
        return response()->json([
            'data' => $data ?? [],
        ]);
    }

    /**
     * Return a standardized API error response using SeamlessWalletCode.
     */
    public static function error(SeamlessWalletCode $code, string $message, mixed $data = [])
    {
        return [
            'code' => $code->value,
            'message' => $message,
            'data' => $data ?? [],
        ];
    }

    

    /**
     * ✅ GPlus success format — only returns { data: [...] }
     */
    public static function gplusSuccess(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * ✅ GPlus error fallback — also returns inside data array (for consistency)
     */
    public static function gplusError(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ]);
    }
}
