<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized action. || ဤလုပ်ဆောင်ချက်အား သင့်မှာ လုပ်ဆောင်ပိုင်ခွင့်မရှိပါ, ကျေးဇူးပြု၍ သက်ဆိုင်ရာ Agent များထံ ဆက်သွယ်ပါ');
        }

        $userType = $this->resolveUserType($user->type);

        if ($user->hasRole('Owner') || $userType === UserType::Owner) {
            return $next($request);
        }

        if (! $userType || ! in_array($userType, [UserType::Agent, UserType::SystemWallet], true)) {
            abort(403, 'Unauthorized action. || ဤလုပ်ဆောင်ချက်အား သင့်မှာ လုပ်ဆောင်ပိုင်ခွင့်မရှိပါ, ကျေးဇူးပြု၍ သက်ဆိုင်ရာ Agent များထံ ဆက်သွယ်ပါ');
        }

        $requiredPermissions = array_filter(explode('|', $permission));

        foreach ($requiredPermissions as $requiredPermission) {
            if ($user->hasPermission($requiredPermission)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action. || ဤလုပ်ဆောင်ချက်အား သင့်မှာ လုပ်ဆောင်ပိုင်ခွင့်မရှိပါ, ကျေးဇူးပြု၍ သက်ဆိုင်ရာ Agent များထံ ဆက်သွယ်ပါ');
    }

    private function resolveUserType(int|string|null $type): ?UserType
    {
        if ($type === null || $type === '') {
            return null;
        }

        try {
            return UserType::from((int) $type);
        } catch (\ValueError) {
            return null;
        }
    }
}
