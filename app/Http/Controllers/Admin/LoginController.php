<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Admin\UserLog;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'user_name' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $this->credentials($request);

        if (! Auth::attempt($credentials)) {
            return back()->with('error', 'The credentials does not match our records.');
        }

        $user = Auth::user();

        try {
            $userType = UserType::from((int) $user->type);
        } catch (\ValueError $e) {
            Auth::logout();

            return back()->with('error', 'You do not have permission to access the admin portal.');
        }

        if (! in_array($userType, [UserType::Owner, UserType::Agent, UserType::SystemWallet], true)) {
            Auth::logout();

            return back()->with('error', 'You do not have permission to access the admin portal.');
        }

        if ($user->status == 0) {
            return redirect()->back()->with('error', 'Your account is not activated!');
        }

        UserLog::create([
            'ip_address' => $request->ip(),
            'user_id' => $user->id,
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        return redirect('/login');
    }

    public function updatePassword(Request $request, User $user)
    {
        try {
            $request->validate([
                'password' => 'required|min:6|confirmed',
            ]);

            $user->update([
                'password' => Hash::make($request->password),
                'is_changed_password' => true,
            ]);

            return redirect()->route('login')->with('success', 'Password has been Updated.');
        } catch (Exception $e) {

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    protected function credentials(Request $request)
    {
        return $request->only('user_name', 'password');
    }
}
