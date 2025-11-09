<?php

namespace App\Http\Controllers;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Models\Admin\UserLog;
use App\Models\TransferLog;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();
        $userType = UserType::from((int) $user->type);

        $totalBalance = $this->calculateDownlineBalance($user, $userType);
        $playerBalance = $this->calculatePlayerBalance($user, $userType);

        $totalWinlose = 0;
        $todayWinlose = 0;
        $todayDeposit = 0;
        $todayWithdraw = 0;

        if ($userType === UserType::Agent) {
            $todayWinlose = $this->getWinLose($user->id, true);
            $totalWinlose = $this->getWinLose($user->id);
            $todayDeposit = $this->fetchTotalTransactions($user->id, 'deposit');
            $todayWithdraw = $this->fetchTotalTransactions($user->id, 'withdraw');
        }

        $userCounts = $this->userCountGet($user, $userType);

        return view('admin.dashboard', [
            'user' => $user,
            'role' => $userType->name,
            'totalBalance' => $totalBalance,
            'playerBalance' => $playerBalance,
            'totalOwner' => $userCounts['totalOwner'] ?? 0,
            'totalAgent' => $userCounts['totalAgent'] ?? 0,
            'totalPlayer' => $userCounts['totalPlayer'] ?? 0,
            'totalWinlose' => $totalWinlose,
            'todayWinlose' => $todayWinlose,
            'todayDeposit' => $todayDeposit,
            'todayWithdraw' => $todayWithdraw,
        ]);
    }

    private function fetchTotalTransactions($id, string $type): float
    {
        $user = User::find($id);
        if (! $user) {
            return 0;
        }

        $query = TransferLog::query()->whereDate('created_at', today());

        if ($type === 'deposit') {
            $query->where('from_user_id', $id);
        } elseif ($type === 'withdraw') {
            $query->where('to_user_id', $id);
        } else {
            return 0;
        }

        return (float) $query->sum('amount');
    }

    private function userCountGet(User $user, UserType $userType): array
    {
        $totalOwner = 0;
        $totalAgent = 0;
        $totalPlayer = 0;

        if ($userType === UserType::Owner) {
            $totalOwner = User::where('type', UserType::Owner->value)->count();

            $agentIds = User::where('agent_id', $user->id)
                ->where('type', UserType::Agent->value)
                ->pluck('id');

            $totalAgent = $agentIds->count();
            $totalPlayer = User::whereIn('agent_id', $agentIds)
                ->where('type', UserType::Player->value)
                ->count();
        } elseif ($userType === UserType::Agent) {
            $totalAgent = 1;
            $totalPlayer = User::where('agent_id', $user->id)
                ->where('type', UserType::Player->value)
                ->count();
        }

        return compact('totalOwner', 'totalAgent', 'totalPlayer');
    }

    private function getWinLose($id, $todayOnly = false): float
    {
        $query = DB::table('reports')
            ->select(
                DB::raw('COALESCE(SUM(reports.bet_amount), 0) as total_bet_amount'),
                DB::raw('COALESCE(SUM(reports.payout_amount), 0) as total_payout_amount')
            )
            ->where('reports.agent_id', $id);

        if ($todayOnly) {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $report = $query->first();

        return $report->total_bet_amount - $report->total_payout_amount;
    }

    public function balanceUp(Request $request)
    {
        $request->validate([
            'balance' => 'required|numeric|min:1',
        ]);

        $admin = Auth::user();
        if (UserType::from((int) $admin->type) !== UserType::Owner) {
            abort(
                Response::HTTP_FORBIDDEN,
                'Unauthorized action. || ဤလုပ်ဆောင်ချက်အား သင့်မှာ လုပ်ဆောင်ပိုင်ခွင့်မရှိပါ, ကျေးဇူးပြု၍ သက်ဆိုင်ရာ Agent များထံ ဆက်သွယ်ပါ'
            );
        }

        app(WalletService::class)->deposit($admin, (int) $request->balance, TransactionName::CapitalDeposit);

        return back()->with('success', 'Add New Balance Successfully.');
    }

    public function changePassword(Request $request, User $user)
    {
        return view('admin.change_password', compact('user'));
    }

    public function changePlayerSite(Request $request, User $user)
    {
        return view('admin.change_player_site', compact('user'));
    }

    public function updatePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('home')->with('success', 'Password has been changed Successfully.');
    }

    public function updatePlayerSiteLink(Request $request, User $user)
    {
        $request->validate([
            'site_link' => 'required|string',
        ]);
        $user->update([
            'site_link' => $request->site_link,
        ]);

        return redirect()->route('home')->with('success', 'Player Site Link has been changed Successfully.');
    }

    public function logs($id)
    {
        $logs = UserLog::with('user')->where('user_id', $id)->get();

        return view('admin.logs', compact('logs'));
    }

    private function calculateDownlineBalance(User $user, UserType $userType): float
    {
        if ($userType === UserType::Owner) {
            return (float) User::where('agent_id', $user->id)
                ->where('type', UserType::Agent->value)
                ->sum('balance');
        }

        if ($userType === UserType::Agent) {
            return (float) User::where('agent_id', $user->id)
                ->where('type', UserType::Player->value)
                ->sum('balance');
        }

        return 0.0;
    }

    private function calculatePlayerBalance(User $user, UserType $userType): float
    {
        if ($userType === UserType::Owner) {
            $agentIds = User::where('agent_id', $user->id)
                ->where('type', UserType::Agent->value)
                ->pluck('id');

            return (float) User::whereIn('agent_id', $agentIds)
                ->where('type', UserType::Player->value)
                ->sum('balance');
        }

        if ($userType === UserType::Agent) {
            return (float) User::where('agent_id', $user->id)
                ->where('type', UserType::Player->value)
                ->sum('balance');
        }

        return 0.0;
    }

}
