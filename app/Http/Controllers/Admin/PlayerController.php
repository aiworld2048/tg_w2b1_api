<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerRequest;
use App\Http\Requests\TransferLogRequest;
use App\Models\TransferLog;
use App\Models\User;
use App\Services\WalletService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PlayerController extends Controller
{
    private const PLAYER_ROLE = 3;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currentUser = Auth::user();
        if ($currentUser && UserType::from((int) $currentUser->type) === UserType::Agent) {
            return redirect()->route('admin.agent.players.index');
        }

        return redirect()->route('admin.players.grouped');
    }

    public function groupedIndex()
    {
        $user = Auth::user();
        $userType = UserType::from((int) $user->type);

        $agentQuery = User::query()
            ->where('type', UserType::Agent->value)
            ->withCount(['children as player_count' => function ($query) {
                $query->where('type', UserType::Player->value);
            }])
            ->withSum(['children as player_balance_sum' => function ($query) {
                $query->where('type', UserType::Player->value);
            }], 'balance');

        if ($userType === UserType::Owner) {
            $agentQuery->where('agent_id', $user->id);
        } elseif ($userType === UserType::Agent) {
            $agentQuery->where('id', $user->id);
        } else {
            abort(
                Response::HTTP_FORBIDDEN,
                'Unauthorized action. || ဤလုပ်ဆောင်ချက်အား သင့်မှာ လုပ်ဆောင်ပိုင်ခွင့်မရှိပါ, ကျေးဇူးပြု၍ သက်ဆိုင်ရာ Agent များထံ ဆက်သွယ်ပါ'
            );
        }

        $agents = $agentQuery->orderBy('name')->get();

        return view('admin.player.grouped_index', compact('agents'));
    }

    public function groupedShow(User $agent)
    {
        $currentUser = Auth::user();
        $currentType = UserType::from((int) $currentUser->type);

        if ($currentType === UserType::Owner && (int) $agent->agent_id !== (int) $currentUser->id) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }

        if ($currentType === UserType::Agent && $agent->id !== $currentUser->id) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }

        if ($agent->type !== UserType::Agent->value) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }

        $players = User::where('agent_id', $agent->id)
            ->where('type', UserType::Player->value)
            ->select('id', 'name', 'user_name', 'phone', 'status', 'balance')
            ->orderBy('name')
            ->get();

        return view('admin.player.grouped_show', compact('agent', 'players'));
    }

    /**
     * Display a listing of the users with their agents.
     *
     * @return \Illuminate\View\View
     */
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $currentUser = Auth::user();
        $currentType = UserType::from((int) $currentUser->type);

        if ($currentType !== UserType::Owner && $currentType !== UserType::Agent) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }

        $playerName = $this->generateRandomString();

        $selectedAgentId = null;

        if ($currentType === UserType::Owner) {
            $agents = User::where('agent_id', $currentUser->id)
                ->where('type', UserType::Agent->value)
                ->select('id', 'name', 'user_name')
                ->orderBy('name')
                ->get();
            $selectedAgentId = request('agent_id');
        } else {
            $agents = collect([$currentUser]);
        }

        return view('admin.player.create', compact('playerName', 'agents', 'currentType', 'selectedAgentId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PlayerRequest $request)
    {

        $currentUser = Auth::user();
        $currentType = UserType::from((int) $currentUser->type);

        $inputs = $request->validated();

        // Set default amount to 0 if not provided
        $inputs['amount'] = $inputs['amount'] ?? 0;

        try {
            DB::beginTransaction();
            $agent = $currentType === UserType::Owner
                ? User::where('id', $inputs['agent_id'])
                    ->where('agent_id', $currentUser->id)
                    ->where('type', UserType::Agent->value)
                    ->firstOrFail()
                : $currentUser;

            if ($inputs['amount'] > (int) $agent->balance) {
                return redirect()->back()->with('error', 'Balance Insufficient');
            }

            $user = User::create([
                'name' => $inputs['name'],
                'user_name' => $inputs['user_name'],
                'password' => Hash::make($inputs['password']),
                'phone' => $inputs['phone'],
                'agent_id' => $agent->id,
                'type' => UserType::Player->value,
                'status' => 1,
            ]);

            $user->roles()->sync(self::PLAYER_ROLE);

            $amount = (int) $inputs['amount'];
            $oldPlayerBalance = (float) $user->balance;

            if ($amount > 0) {
                app(WalletService::class)->transfer($agent, $user, $amount,
                    TransactionName::CreditTransfer, [
                        'note' => 'Initial top up',
                        'old_balance' => $oldPlayerBalance,
                        'new_balance' => $oldPlayerBalance + $amount,
                    ]);
                $user->refresh();

                TransferLog::create([
                    'from_user_id' => $agent->id,
                    'to_user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'top_up',
                    'description' => 'Initial top up from '.$agent->user_name.' to player',
                    'meta' => [
                        'transaction_type' => TransactionName::CreditTransfer->value,
                        'old_balance' => $oldPlayerBalance,
                        'new_balance' => (float) $user->balance,
                    ],
                ]);
            }

            DB::commit();

            return redirect()->route('players.grouped.show', $agent->id)
                ->with('successMessage', 'Player created successfully')
                ->with('amount', $inputs['amount'])
                ->with('password', $request->password)
                ->with('user_name', $user->user_name);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: '.$e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while creating the player.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(Response::HTTP_NOT_FOUND);
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Remove the specified resource from storage.
     */
    public function getCashIn(User $player)
    {
        $fundingAgent = $this->resolveFundingAgent($player);

        return view('admin.player.cash_in', compact('player', 'fundingAgent'));
    }

    public function makeCashIn(TransferLogRequest $request, User $player)
    {
        try {
            DB::beginTransaction();
            $inputs = $request->validated();
            $inputs['refrence_id'] = $this->getRefrenceId();

            $fundingAgent = $this->resolveFundingAgent($player);

            $amount = (int) $inputs['amount'];

            if ($amount > (int) $fundingAgent->balance) {

                return redirect()->back()->with('error', 'You do not have enough balance to transfer!');
            }

            $oldPlayerBalance = (float) $player->balance;

            app(WalletService::class)->transfer($fundingAgent, $player, $amount,
                TransactionName::CreditTransfer, [
                    'note' => $request->note,
                    'old_balance' => $oldPlayerBalance,
                    'new_balance' => $oldPlayerBalance + $amount,
                ]);

            $player->refresh();

            TransferLog::create([
                'from_user_id' => $fundingAgent->id,
                'to_user_id' => $player->id,
                'amount' => $amount,
                'type' => 'top_up',
                'description' => 'Credit transfer from '.$fundingAgent->user_name.' to player',
                'meta' => [
                    'transaction_type' => TransactionName::Deposit->value,
                    'note' => $request->note,
                    'old_balance' => $oldPlayerBalance,
                    'new_balance' => (float) $player->balance,
                ],
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'CashIn submitted successfully!');
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getCashOut(User $player)
    {
        $fundingAgent = $this->resolveFundingAgent($player);

        return view('admin.player.cash_out', compact('player', 'fundingAgent'));
    }

    public function makeCashOut(TransferLogRequest $request, User $player)
    {
        try {
            DB::beginTransaction();
            $inputs = $request->validated();
            $inputs['refrence_id'] = $this->getRefrenceId();

            $fundingAgent = $this->resolveFundingAgent($player);

            $amount = (int) $inputs['amount'];

            if ($amount > (int) $player->balance) {

                return redirect()->back()->with('error', 'You do not have enough balance to transfer!');
            }

            $oldPlayerBalance = (float) $player->balance;

            app(WalletService::class)->transfer($player, $fundingAgent, $amount,
                TransactionName::DebitTransfer, [
                    'note' => $request->note,
                    'old_balance' => $oldPlayerBalance,
                    'new_balance' => $oldPlayerBalance - $amount,
                ]);

            $player->refresh();

            TransferLog::create([
                'from_user_id' => $player->id,
                'to_user_id' => $fundingAgent->id,
                'amount' => $amount,
                'type' => 'withdraw',
                'description' => 'Credit transfer from player to '.$fundingAgent->user_name,
                'meta' => [
                    'transaction_type' => TransactionName::Withdraw->value,
                    'note' => $request->note,
                    'old_balance' => $oldPlayerBalance,
                    'new_balance' => (float) $player->balance,
                ],
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'CashOut submitted successfully!');
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function banUser($id)
    {
        $player = User::where('id', $id)
            ->where('type', UserType::Player->value)
            ->firstOrFail();

        $current = Auth::user();
        $currentType = UserType::from((int) $current->type);

        if ($currentType === UserType::Owner) {
            $allowedAgentIds = User::where('agent_id', $current->id)
                ->where('type', UserType::Agent->value)
                ->pluck('id');

            if (! $allowedAgentIds->contains($player->agent_id)) {
                abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
            }
        } elseif ($currentType === UserType::Agent) {
            if ($player->agent_id !== $current->id) {
                abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
            }
        } else {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }

        $player->update(['status' => $player->status == 1 ? 0 : 1]);

        return redirect()->back()->with(
            'success',
            'User '.($player->status == 1 ? 'activate' : 'inactive').' successfully'
        );
    }

    public function getChangePassword($id)
    {
        $player = User::where('id', $id)
            ->where('type', UserType::Player->value)
            ->firstOrFail();

        $current = Auth::user();
        $currentType = UserType::from((int) $current->type);

        if ($currentType === UserType::Owner) {
            $allowedAgentIds = User::where('agent_id', $current->id)
                ->where('type', UserType::Agent->value)
                ->pluck('id');

            if (! $allowedAgentIds->contains($player->agent_id)) {
                abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
            }
        } elseif ($currentType === UserType::Agent) {
            if ($player->agent_id !== $current->id) {
                abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
            }
        }

        return view('admin.player.change_password', compact('player'));
    }

    public function makeChangePassword($id, Request $request)
    {
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $player = User::where('id', $id)
            ->where('type', UserType::Player->value)
            ->firstOrFail();
        $player->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()
            ->with('success', 'Player Change Password successfully')
            ->with('password', $request->password)
            ->with('username', $player->user_name);
    }

    private function generateRandomString()
    {
        $randomNumber = mt_rand(10000000, 99999999);

        return 'P'.$randomNumber;
    }

    private function getRefrenceId($prefix = 'REF')
    {
        return uniqid($prefix);
    }

    private function resolveFundingAgent(User $player): User
    {
        $current = Auth::user();
        $currentType = UserType::from((int) $current->type);

        if ($currentType === UserType::Agent) {
            if ($player->agent_id !== $current->id) {
                abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
            }

            return $current;
        }

        if ($currentType === UserType::Owner) {
            $agent = User::where('id', $player->agent_id)
                ->where('agent_id', $current->id)
                ->where('type', UserType::Agent->value)
                ->firstOrFail();

            return $agent;
        }

        abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
    }
}
