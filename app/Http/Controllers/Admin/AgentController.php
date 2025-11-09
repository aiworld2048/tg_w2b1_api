<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentRequest;
use App\Http\Requests\TransferLogRequest;
// use App\Models\Admin\TransferLog;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\PaymentType;
use App\Models\TransferLog;
use App\Models\User;
use App\Services\WalletService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AgentController extends Controller
{
    private const DEFAULT_AGENT_PERMISSION_TITLES = [
        'player_index',
        'player_create',
        'player_edit',
        'player_delete',
        'player_view',
        'deposit',
        'withdraw',
        'contact',
        'transfer_log',
        'make_transfer',
        'bank',
    ];

    public function __construct()
    {
        $this->middleware('permission:agent_index')->only(['index']);
        $this->middleware('permission:agent_create')->only(['create', 'store']);
        $this->middleware('permission:agent_edit')->only(['edit', 'update', 'banAgent']);
        $this->middleware('permission:agent_change_password_access')->only(['getChangePassword', 'makeChangePassword']);
        $this->middleware('permission:make_transfer')->only(['getCashIn', 'makeCashIn', 'getCashOut', 'makeCashOut']);
    }

    public function index(): View
    {
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $users = User::with(['roles', 'children.poneWinePlayer'])
            ->where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->select('id', 'name', 'user_name', 'phone', 'status', 'referral_code', 'balance', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.agent.index', compact('users'));
    }

    

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // if (! Gate::allows('agent_create')) {
        //     abort(403);
        // }

        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent_name = $this->generateRandomString();
        $paymentTypes = PaymentType::all();
        $referral_code = $this->generateReferralCode();

        return view('admin.agent.create', compact('agent_name', 'paymentTypes', 'referral_code'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AgentRequest $request): RedirectResponse
    {
        // if (! Gate::allows('agent_create')) {
        //     abort(403);
        // }

        $owner = Auth::user();
        $this->ensureOwner($owner);
        $inputs = $request->validated();

        $transfer_amount = (int) ($inputs['amount'] ?? 0);

        if ($transfer_amount < 0) {
            return redirect()->back()->with('error', 'Amount must be greater than or equal to zero.');
        }

        if ($transfer_amount > 0 && $transfer_amount > (int) $owner->balance) {
            return redirect()->back()->with('error', 'Balance Insufficient');
        }

        try {
            DB::beginTransaction();

            $agent = User::create([
                'user_name' => $inputs['user_name'],
                'name' => $inputs['name'],
                'phone' => $inputs['phone'],
                'password' => Hash::make($inputs['password']),
                'referral_code' => $inputs['referral_code'],
                'agent_id' => $owner->id,
                'type' => UserType::Agent->value,
                'status' => 1,
            ]);

            $this->assignAgentRole($agent);
            $this->assignAgentPermissions($agent);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create agent: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to create agent. Please try again.');
        }

        if ($transfer_amount > 0) {
            try {
                app(WalletService::class)->transfer(
                    $owner->fresh(),
                    $agent->fresh(),
                    $transfer_amount,
                    TransactionName::CreditTransfer,
                    [
                        'description' => 'Initial top up from owner',
                    ]
                );
            } catch (Exception $e) {
                Log::error('Failed to transfer initial balance to agent: '.$e->getMessage());

                return redirect()->route('admin.agent.index')
                    ->with('successMessage', 'Agent created successfully but initial transfer failed. Please retry manually.')
                    ->with('password', $request->password)
                    ->with('username', $agent->user_name)
                    ->with('amount', 0)
                    ->with('link', config('app.url').'/login');
            }
        }

        return redirect()->route('admin.agent.index')
            ->with('successMessage', 'Agent created successfully')
            ->with('password', $request->password)
            ->with('username', $agent->user_name)
            ->with('amount', $transfer_amount)
            ->with('link', config('app.url').'/login');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        // if (! Gate::allows('agent_edit')) {
        //     abort(403);
        // }

        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);
        $paymentTypes = PaymentType::all();

        return view('admin.agent.edit', compact('agent', 'paymentTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        // if (! Gate::allows('agent_edit')) {
        //     abort(403);
        // }

        $owner = Auth::user();
        $this->ensureOwner($owner);

        $user = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        $user->update($request->all());

        return redirect()->route('admin.agent.index')
            ->with('success', 'Agent Updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function getCashIn(string $id): View
    {
        // if (! Gate::allows('make_transfer')) {
        //     abort(403);
        // }
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        return view('admin.agent.cash_in', compact('agent'));
    }

    public function getCashOut(string $id): View
    {
        // if (! Gate::allows('make_transfer')) {
        //     abort(403);
        // }
        // Assuming $id is the user ID
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        return view('admin.agent.cash_out', compact('agent'));
    }

    public function makeCashIn(Request $request, $id): RedirectResponse
    {
        // if (! Gate::allows('make_transfer')) {
        //     abort(403);
        // }

        try {
            $owner = Auth::user();
            $this->ensureOwner($owner);

            $agent = User::where('type', UserType::Agent->value)
                ->where('agent_id', $owner->id)
                ->findOrFail($id);

            $request->validate([
                'amount' => ['required', 'numeric', 'min:1'],
                'note' => ['nullable', 'string', 'max:255'],
            ]);

            $amount = (int) $request->amount;

            if ($amount > (int) $owner->balance) {
                throw new \Exception('You do not have enough balance to transfer!');
            }

            app(WalletService::class)->transfer(
                $owner,
                $agent,
                $amount,
                TransactionName::CreditTransfer,
                [
                    'note' => $request->note,
                    'description' => $request->note ?? 'Owner to agent top up',
                ]
            );

            return redirect()->route('admin.agent.index')->with('success', 'Money fill request submitted successfully!');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function makeCashOut(TransferLogRequest $request, string $id): RedirectResponse
    {
        // if (! Gate::allows('make_transfer')) {
        //     abort(403);
        // }

        try {
            $owner = Auth::user();
            $this->ensureOwner($owner);

            $agent = User::where('type', UserType::Agent->value)
                ->where('agent_id', $owner->id)
                ->findOrFail($id);

            $request->validate([
                'amount' => ['required', 'numeric', 'min:1'],
                'note' => ['nullable', 'string', 'max:255'],
            ]);

            $amount = (int) $request->amount;

            if ($amount > (int) $agent->balance) {
                return redirect()->back()->with('error', 'You do not have enough balance to transfer!');
            }

            app(WalletService::class)->transfer(
                $agent,
                $owner,
                $amount,
                TransactionName::DebitTransfer,
                [
                    'note' => $request->note,
                    'description' => $request->note ?? 'Agent cash out to owner',
                ]
            );

            return redirect()->back()->with('success', 'Money fill request submitted successfully!');
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getTransferDetail($id)
    {
       
        $transfer_detail = TransferLog::where('from_user_id', $id)
            ->orWhere('to_user_id', $id)
            ->get();

        return view('admin.agent.transfer_detail', compact('transfer_detail'));
    }

    private function generateRandomString()
    {
        $randomNumber = mt_rand(10000000, 99999999);

        return 'AG'.$randomNumber;
    }

    public function banAgent($id): RedirectResponse
    {
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $user = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);
        $user->update(['status' => $user->status == 1 ? 0 : 1]);

        return redirect()->back()->with(
            'success',
            'User '.($user->status == 1 ? 'activate' : 'inactive').' successfully'
        );
    }

    public function getChangePassword($id)
    {
        // abort_if(
        //     Gate::denies('owner_access') || ! $this->ifChildOfParent(request()->user()->id, $id),
        //     Response::HTTP_FORBIDDEN,
        //     '403 Forbidden |You cannot  Access this page because you do not have permission'
        // );

        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        return view('admin.agent.change_password', compact('agent'));
    }

    public function makeChangePassword($id, Request $request)
    {
        // abort_if(
        //     Gate::denies('owner_access') || ! $this->ifChildOfParent(request()->user()->id, $id),
        //     Response::HTTP_FORBIDDEN,
        //     '403 Forbidden |You cannot  Access this page because you do not have permission'
        // );

        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);
        $agent->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.agent.index')
            ->with('successMessage', 'Agent Change Password successfully')
            ->with('password', $request->password)
            ->with('username', $agent->user_name);
    }

    public function showAgentLogin($id)
    {
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        return view('auth.agent_login', compact('agent'));
    }

    public function AgentToPlayerDepositLog()
    {
        $transactions = DB::table('transactions')
            ->join('users as players', 'players.id', '=', 'transactions.payable_id')
            ->join('users as agents', 'agents.id', '=', 'players.agent_id')
            ->where('transactions.type', 'deposit')
            ->where('transactions.name', 'credit_transfer')
            ->where('agents.id', '<>', 1) // Exclude agent_id 1
            ->groupBy('agents.id', 'players.id', 'agents.name', 'players.name', 'agents.commission')
            ->select(
                'agents.id as agent_id',
                'agents.name as agent_name',
                'players.id as player_id',
                'players.name as player_name',
                'agents.commission as agent_commission', // Get the commission percentage
                DB::raw('count(transactions.id) as total_deposits'),
                DB::raw('sum(transactions.amount) as total_amount')
            )
            ->get();

        return view('admin.agent.agent_to_play_dep_log', compact('transactions'));
    }

    public function AgentToPlayerDetail($agent_id, $player_id)
    {
        // Retrieve detailed information about the agent and player
        $transactionDetails = DB::table('transactions')
            ->join('users as players', 'players.id', '=', 'transactions.payable_id')
            ->join('users as agents', 'agents.id', '=', 'players.agent_id')
            ->where('agents.id', $agent_id)
            ->where('players.id', $player_id)
            ->where('transactions.type', 'deposit')
            ->where('transactions.name', 'credit_transfer')
            ->select(
                'agents.name as agent_name',
                'players.name as player_name',
                'transactions.amount',
                'transactions.created_at',
                'agents.commission as agent_commission'
            )
            ->get();

        return view('admin.agent.agent_to_player_detail', compact('transactionDetails'));
    }

    public function AgentWinLoseReport(Request $request)
    {
        $query = DB::table('reports')
            ->join('users', 'reports.agent_id', '=', 'users.id')
            ->select(
                'reports.agent_id',
                'users.name as agent_name',
                DB::raw('COUNT(DISTINCT reports.id) as qty'),
                DB::raw('SUM(reports.bet_amount) as total_bet_amount'),
                DB::raw('SUM(reports.valid_bet_amount) as total_valid_bet_amount'),
                DB::raw('SUM(reports.payout_amount) as total_payout_amount'),
                DB::raw('SUM(reports.commission_amount) as total_commission_amount'),
                DB::raw('SUM(reports.jack_pot_amount) as total_jack_pot_amount'),
                DB::raw('SUM(reports.jp_bet) as total_jp_bet'),
                DB::raw('(SUM(reports.payout_amount) - SUM(reports.valid_bet_amount)) as win_or_lose'),
                DB::raw('COUNT(*) as stake_count'),
                DB::raw('DATE_FORMAT(reports.created_at, "%Y %M") as report_month_year')
            );

        // Apply the date filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('reports.created_at', [$request->start_date, $request->end_date]);
        } elseif ($request->has('month_year')) {
            // Filter by month and year if provided
            $monthYear = Carbon::parse($request->month_year);
            $query->whereMonth('reports.created_at', $monthYear->month)
                ->whereYear('reports.created_at', $monthYear->year);
        }

        $agentReports = $query->groupBy('reports.agent_id', 'users.name', 'report_month_year')->get();

        return view('admin.agent.agent_report_index', compact('agentReports'));
    }

    public function AgentWinLoseDetails($agent_id, $month)
    {
        $details = DB::table('reports')
            ->join('users', 'reports.agent_id', '=', 'users.id')
            ->where('reports.agent_id', $agent_id)
            ->whereMonth('reports.created_at', Carbon::parse($month)->month)
            ->whereYear('reports.created_at', Carbon::parse($month)->year)
            ->select(
                'reports.*',
                'users.name as agent_name',
                'users.commission as agent_comm',
                DB::raw('(reports.payout_amount - reports.valid_bet_amount) as win_or_lose') // Calculating win_or_lose
            )
            ->get();

        return view('admin.agent.win_lose_details', compact('details'));
    }

    public function AuthAgentWinLoseReport(Request $request)
    {
        $agentId = Auth::user()->id;  // Get the authenticated user's agent_id

        $query = DB::table('reports')
            ->join('users', 'reports.agent_id', '=', 'users.id')
            ->select(
                'reports.agent_id',
                'users.name as agent_name',
                DB::raw('COUNT(DISTINCT reports.id) as qty'),
                DB::raw('SUM(reports.bet_amount) as total_bet_amount'),
                DB::raw('SUM(reports.valid_bet_amount) as total_valid_bet_amount'),
                DB::raw('SUM(reports.payout_amount) as total_payout_amount'),
                DB::raw('SUM(reports.commission_amount) as total_commission_amount'),
                DB::raw('SUM(reports.jack_pot_amount) as total_jack_pot_amount'),
                DB::raw('SUM(reports.jp_bet) as total_jp_bet'),
                DB::raw('(SUM(reports.payout_amount) - SUM(reports.valid_bet_amount)) as win_or_lose'),
                DB::raw('COUNT(*) as stake_count'),
                DB::raw('DATE_FORMAT(reports.created_at, "%Y %M") as report_month_year')
            )
            ->where('reports.agent_id', $agentId);  // Filter by authenticated user's agent_id

        // Apply the date filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('reports.created_at', [$request->start_date, $request->end_date]);
        } elseif ($request->has('month_year')) {
            // Filter by month and year if provided
            $monthYear = Carbon::parse($request->month_year);
            $query->whereMonth('reports.created_at', $monthYear->month)
                ->whereYear('reports.created_at', $monthYear->year);
        }

        $agentReports = $query->groupBy('reports.agent_id', 'users.name', 'report_month_year')->get();

        return view('admin.agent.auth_agent_report_index', compact('agentReports'));
    }

    public function AuthAgentWinLoseDetails($agent_id, $month)
    {
        $details = DB::table('reports')
            ->join('users', 'reports.agent_id', '=', 'users.id')
            ->where('reports.agent_id', $agent_id)
            ->whereMonth('reports.created_at', Carbon::parse($month)->month)
            ->whereYear('reports.created_at', Carbon::parse($month)->year)
            ->select(
                'reports.*',
                'users.name as agent_name',
                'users.commission as agent_comm',
                DB::raw('(reports.payout_amount - reports.valid_bet_amount) as win_or_lose') // Calculating win_or_lose
            )
            ->get();

        return view('admin.agent.auth_win_lose_details', compact('details'));
    }

    public function getPlayerReports($id)
    {
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $agent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        $users = User::with('roles', 'poneWinePlayer', 'results', 'betNResults')
            ->where('type', UserType::Player->value)
            ->where('agent_id', $agent->id)
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.agent.player_report', compact('users'));
    }

    public function agentReportIndex($id)
    {
        // Eager-load agent, roles, and children (downlines) with their poneWinePlayer relationship
        $agent = User::with([
            'roles',
            'children.poneWinePlayer',
        ])->findOrFail($id);

        // Sum all win_lose_amt for all poneWinePlayer of all children
        $poneWineTotalAmt = $agent->children
            ->flatMap(function ($child) {
                return $child->poneWinePlayer;
            })
            ->sum('win_lose_amt');

        // Query total bet and payout for the agent's downline players (only slot games, if you want)
        $reportData = DB::table('users as a')
            ->join('users as p', 'p.agent_id', '=', 'a.id')
            ->join('place_bets', 'place_bets.member_account', '=', 'p.user_name')
            ->where('a.id', $id)
            ->groupBy('a.id')
            ->selectRaw('
            a.id as agent_id,
            SUM(place_bets.bet_amount) as total_bet_amount,
            SUM(place_bets.prize_amount) as total_payout_amount
        ')
            ->first();

        // Add agent info to the report
        $report = [
            'agent_name' => $agent->name,
            'agent_user_name' => $agent->user_name,
            'win_lose' => ($reportData->total_bet_amount ?? 0) - ($reportData->total_payout_amount ?? 0),
            'total_win_lose_pone_wine' => $poneWineTotalAmt ?? 0,
        ];

        return view('admin.agent.report_index', compact('report'));
    }

    private function generateReferralCode($length = 8)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    // agent profile
    public function agentProfile($id)
    {
        $owner = Auth::user();
        $this->ensureOwner($owner);

        $subAgent = User::where('type', UserType::Agent->value)
            ->where('agent_id', $owner->id)
            ->findOrFail($id);

        return view('admin.agent.agent_profile', compact('subAgent'));
    }

    private function ensureOwner(User $user): void
    {
        if ((int) $user->type !== UserType::Owner->value) {
            abort(
                Response::HTTP_FORBIDDEN,
                'Unauthorized action. || ဤလုပ်ဆောင်ချက်အား သင့်မှာ လုပ်ဆောင်ပိုင်ခွင့်မရှိပါ, ကျေးဇူးပြု၍ သက်ဆိုင်ရာ Agent များထံ ဆက်သွယ်ပါ'
            );
        }
    }

    private function assignAgentRole(User $agent): void
    {
        $roleId = Role::where('title', 'Agent')->value('id');

        if ($roleId) {
            $agent->roles()->sync($roleId);
        }
    }

    private function assignAgentPermissions(User $agent): void
    {
        $permissionIds = Permission::whereIn('title', self::DEFAULT_AGENT_PERMISSION_TITLES)->pluck('id');
        $agent->permissions()->sync($permissionIds);
    }
}
