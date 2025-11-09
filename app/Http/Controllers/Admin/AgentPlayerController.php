<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionName;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerRequest;
use App\Http\Requests\TransferLogRequest;
use App\Models\PlaceBet;
use App\Models\TransferLog;
use App\Models\User;
use App\Models\UserLog;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AgentPlayerController extends Controller
{
    private const PLAYER_ROLE = 3;

    public function index()
    {
        $agent = $this->currentAgent();

        $playerIds = collect(DB::select('
            WITH RECURSIVE descendants AS (
                SELECT id FROM users WHERE id = ?
                UNION ALL
                SELECT u.id FROM users u INNER JOIN descendants d ON u.agent_id = d.id
            )
            SELECT id FROM users
            WHERE id IN (SELECT id FROM descendants)
              AND type = ?
        ', [$agent->id, UserType::Player->value]))->pluck('id');

        $players = User::with('roles')
            ->whereIn('id', $playerIds)
            ->select('id', 'name', 'user_name', 'phone', 'status', 'balance', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        $spinTotals = PlaceBet::query()
            ->selectRaw('player_id, COUNT(DISTINCT wager_code) as total_spin')
            ->whereIn('player_id', $playerIds)
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $betTotals = PlaceBet::query()
            ->selectRaw('
                player_id,
                SUM(CASE
                    WHEN currency = \'MMK2\' THEN COALESCE(bet_amount, 0) * 1000
                    ELSE COALESCE(bet_amount, 0)
                END) as total_bet_amount
            ')
            ->whereIn('player_id', $playerIds)
            ->where('wager_status', 'SETTLED')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $settleTotals = PlaceBet::query()
            ->selectRaw('
                player_id,
                SUM(CASE
                    WHEN currency = \'MMK2\' THEN COALESCE(prize_amount, 0) * 1000
                    ELSE COALESCE(prize_amount, 0)
                END) as total_payout_amount
            ')
            ->whereIn('player_id', $playerIds)
            ->where('wager_status', 'SETTLED')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $transferLogs = TransferLog::with(['fromUser', 'toUser'])
            ->whereIn('from_user_id', $playerIds)
            ->orWhereIn('to_user_id', $playerIds)
            ->latest()
            ->get()
            ->groupBy(function ($log) use ($playerIds) {
                if ($playerIds->contains($log->from_user_id)) {
                    return $log->from_user_id;
                }
                if ($playerIds->contains($log->to_user_id)) {
                    return $log->to_user_id;
                }

            });

        $users = $players->map(function (User $player) use ($spinTotals, $betTotals, $settleTotals, $transferLogs) {
            $spin = $spinTotals->get($player->id);
            $bet = $betTotals->get($player->id);
            $settle = $settleTotals->get($player->id);
            $playerSpecificLogs = $transferLogs->get($player->id, collect());

            return (object) [
                'id' => $player->id,
                'name' => $player->name,
                'user_name' => $player->user_name,
                'phone' => $player->phone,
                'balance' => $player->balance,
                'status' => $player->status,
                'total_spin' => $spin->total_spin ?? 0,
                'total_bet_amount' => $bet->total_bet_amount ?? 0,
                'total_payout_amount' => $settle->total_payout_amount ?? 0,
                'logs' => $playerSpecificLogs,
            ];
        });

        return view('admin.agent_players.index', compact('users'));
    }

    public function create()
    {
        $agent = $this->currentAgent();
        $playerName = $this->generateRandomString();

        return view('admin.player.create', [
            'playerName' => $playerName,
            'agents' => collect([$agent]),
            'currentType' => UserType::Agent,
            'selectedAgentId' => $agent->id,
        ]);
    }

    public function store(PlayerRequest $request)
    {
        $agent = $this->currentAgent();
        $inputs = $request->validated();
        $amount = (int) ($inputs['amount'] ?? 0);

        try {
            DB::beginTransaction();

            if ($amount > (int) $agent->balance) {
                return redirect()->back()->with('error', 'Balance Insufficient');
            }

            $player = User::create([
                'name' => $inputs['name'],
                'user_name' => $inputs['user_name'],
                'password' => Hash::make($inputs['password']),
                'phone' => $inputs['phone'],
                'agent_id' => $agent->id,
                'type' => UserType::Player->value,
                'status' => 1,
            ]);

            $player->roles()->sync(self::PLAYER_ROLE);

            if ($amount > 0) {
                $oldBalance = (float) $player->balance;
                app(WalletService::class)->transfer($agent, $player, $amount, TransactionName::CreditTransfer, [
                    'note' => 'Initial top up',
                    'old_balance' => $oldBalance,
                    'new_balance' => $oldBalance + $amount,
                ]);

                $player->refresh();

                TransferLog::create([
                    'from_user_id' => $agent->id,
                    'to_user_id' => $player->id,
                    'amount' => $amount,
                    'type' => 'top_up',
                    'description' => 'Initial top up from '.$agent->user_name.' to player',
                    'meta' => [
                        'transaction_type' => TransactionName::CreditTransfer->value,
                        'old_balance' => $oldBalance,
                        'new_balance' => (float) $player->balance,
                    ],
                ]);
            }

            DB::commit();

            return redirect()->route('admin.agent.players.index')
                ->with('success', 'Player created successfully.')
                ->with('successMessage', 'Player created successfully.')
                ->with('user_name', $player->user_name)
                ->with('password', $request->password)
                ->with('amount', $amount);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create player', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Failed to create player.');
        }
    }

    public function edit(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        return view('admin.player.edit', compact('player'));
    }

    public function update(Request $request, User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        $validated = $request->validate([
            'name' => ['nullable', 'string'],
            'phone' => ['nullable', 'regex:/^[0-9]+$/', 'unique:users,phone,'.$player->id],
        ]);

        $player->update($validated);

        return redirect()->route('admin.agent.players.index')->with('success', 'Player updated successfully.');
    }

    public function destroy(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);
        $player->delete();

        return redirect()->route('admin.agent.players.index')->with('success', 'Player deleted successfully.');
    }

    public function banUser(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        $player->update(['status' => $player->status == 1 ? 0 : 1]);

        return redirect()->back()->with(
            'success',
            'User '.($player->status == 1 ? 'activated' : 'deactivated').' successfully.'
        );
    }

    public function getCashIn(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        return view('admin.player.cash_in', [
            'player' => $player,
            'fundingAgent' => Auth::user(),
        ]);
    }

    public function makeCashIn(TransferLogRequest $request, User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        try {
            DB::beginTransaction();

            $amount = (int) $request->validated('amount');
            $agent = Auth::user();

            if ($amount > (int) $agent->balance) {
                return redirect()->back()->with('error', 'You do not have enough balance to transfer!');
            }

            $oldBalance = (float) $player->balance;

            app(WalletService::class)->transfer($agent, $player, $amount, TransactionName::CreditTransfer, [
                'note' => $request->note,
                'old_balance' => $oldBalance,
                'new_balance' => $oldBalance + $amount,
            ]);

            $player->refresh();

            TransferLog::create([
                'from_user_id' => $agent->id,
                'to_user_id' => $player->id,
                'amount' => $amount,
                'type' => 'top_up',
                'description' => 'Credit transfer from '.$agent->user_name.' to player',
                'meta' => [
                    'transaction_type' => TransactionName::Deposit->value,
                    'note' => $request->note,
                    'old_balance' => $oldBalance,
                    'new_balance' => (float) $player->balance,
                ],
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Cash in submitted successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getCashOut(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        return view('admin.player.cash_out', [
            'player' => $player,
            'fundingAgent' => Auth::user(),
        ]);
    }

    public function makeCashOut(TransferLogRequest $request, User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        try {
            DB::beginTransaction();

            $amount = (int) $request->validated('amount');
            $agent = Auth::user();

            if ($amount > (int) $player->balance) {
                return redirect()->back()->with('error', 'You do not have enough balance to transfer!');
            }

            $oldBalance = (float) $player->balance;

            app(WalletService::class)->transfer($player, $agent, $amount, TransactionName::DebitTransfer, [
                'note' => $request->note,
                'old_balance' => $oldBalance,
                'new_balance' => $oldBalance - $amount,
            ]);

            $player->refresh();

            TransferLog::create([
                'from_user_id' => $player->id,
                'to_user_id' => $agent->id,
                'amount' => $amount,
                'type' => 'withdraw',
                'description' => 'Credit transfer from player to '.$agent->user_name,
                'meta' => [
                    'transaction_type' => TransactionName::Withdraw->value,
                    'note' => $request->note,
                    'old_balance' => $oldBalance,
                    'new_balance' => (float) $player->balance,
                ],
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Cash out submitted successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getChangePassword($id)
    {
        $player = User::where('id', $id)
            ->where('type', UserType::Player->value)
            ->firstOrFail();

        $this->ensurePlayerBelongsToAgent($player);

        return view('admin.player.change_password', compact('player'));
    }

    public function makeChangePassword($id, Request $request)
    {
        $player = User::where('id', $id)
            ->where('type', UserType::Player->value)
            ->firstOrFail();

        $this->ensurePlayerBelongsToAgent($player);

        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $player->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()
            ->with('success', 'Player password changed successfully')
            ->with('username', $player->user_name)
            ->with('password', $request->password);
    }

    public function loginLogs(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        $logs = UserLog::where('user_id', $player->id)
            ->latest()
            ->paginate(20);

        return view('admin.player.log_detail', compact('player', 'logs'));
    }

    public function report(User $player)
    {
        $this->ensurePlayerBelongsToAgent($player);

        $startDate = request('start_date') ?? now()->startOfDay()->toDateString();
        $endDate = request('end_date') ?? now()->endOfDay()->toDateString();

        $baseQuery = PlaceBet::query()
            ->where('player_id', $player->id)
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

        $reportDetail = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->appends([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $totalRow = (clone $baseQuery)
            ->selectRaw("
                SUM(CASE
                    WHEN currency = 'MMK2' THEN COALESCE(bet_amount, 0) * 1000
                    ELSE COALESCE(bet_amount, 0)
                END) as total_bet_amt,
                SUM(CASE
                    WHEN currency = 'MMK2' THEN COALESCE(prize_amount, 0) * 1000
                    ELSE COALESCE(prize_amount, 0)
                END) as total_payout_amt
            ")
            ->first();

        $total = [
            'total_bet_amt' => (float) ($totalRow->total_bet_amt ?? 0),
            'total_payout_amt' => (float) ($totalRow->total_payout_amt ?? 0),
            'total_net_win' => (float) ($totalRow->total_payout_amt ?? 0) - (float) ($totalRow->total_bet_amt ?? 0),
        ];

        return view('admin.player.report_index', compact('player', 'reportDetail', 'total'));
    }

    private function currentAgent(): User
    {
        $user = Auth::user();
        if (! $user || UserType::from((int) $user->type) !== UserType::Agent) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }

        return $user;
    }

    private function ensurePlayerBelongsToAgent(User $player): void
    {
        $agent = $this->currentAgent();

        if ($player->agent_id !== $agent->id) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized action.');
        }
    }

    private function generateRandomString(): string
    {
        return 'P'.mt_rand(10000000, 99999999);
    }
}
