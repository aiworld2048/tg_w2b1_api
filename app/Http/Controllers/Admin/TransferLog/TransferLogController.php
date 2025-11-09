<?php

namespace App\Http\Controllers\Admin\TransferLog;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\TransferLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TransferLogController extends Controller
{
    protected const OWNER_ROLE = 'Owner';

    protected const AGENT_ROLE = 'Agent';

    protected const PLAYER_ROLE = 'Player';

    public function index(Request $request)
    {
        $agent = $this->getAgentOrCurrentUser();

        [$startDate, $endDate] = $this->parseDateRange($request);

        $transferLogs = $this->fetchTransferLogs($agent, $startDate, $endDate);
        $depositTotal = $this->fetchTotalAmount($agent, 'top_up', $startDate, $endDate);
        $withdrawTotal = $this->fetchTotalAmount($agent, 'withdraw', $startDate, $endDate);

        return view('admin.trans_log.index', compact('transferLogs', 'depositTotal', 'withdrawTotal'));
    }

    private function parseDateRange(Request $request): array
    {
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::today()->startOfDay();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::today()->endOfDay();

        return [$startDate->format('Y-m-d H:i'), $endDate->format('Y-m-d H:i')];
    }

    private function fetchTransferLogs(User $user, string $startDate, string $endDate)
    {
        $relatedUserIds = $this->getRelevantUserIdsForTransfer($user);

        return TransferLog::with(['fromUser', 'toUser'])
            ->where(function ($query) use ($user, $relatedUserIds) {
                $query->where(function ($q) use ($user, $relatedUserIds) {
                    $q->where('from_user_id', $user->id)
                        ->whereIn('to_user_id', $relatedUserIds);
                })->orWhere(function ($q) use ($user, $relatedUserIds) {
                    $q->whereIn('from_user_id', $relatedUserIds)
                        ->where('to_user_id', $user->id);
                });
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('id')
            ->get();
    }

    private function fetchTotalAmount(User $user, string $type, string $startDate, string $endDate): float
    {
        $relatedUserIds = $this->getRelevantUserIdsForTransfer($user);

        return TransferLog::where('type', $type)
            ->where(function ($query) use ($user, $relatedUserIds) {
                $query->where(function ($q) use ($user, $relatedUserIds) {
                    $q->where('from_user_id', $user->id)
                        ->whereIn('to_user_id', $relatedUserIds);
                })->orWhere(function ($q) use ($user, $relatedUserIds) {
                    $q->whereIn('from_user_id', $relatedUserIds)
                        ->where('to_user_id', $user->id);
                });
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }

    private function getRelevantUserIdsForTransfer(User $user): array
    {
        $userType = UserType::from((int) $user->type);

        return match ($userType) {
            UserType::Owner => User::query()
                ->where('agent_id', $user->id)
                ->where('type', UserType::Agent->value)
                ->pluck('id')
                ->toArray(),
            UserType::Agent => array_unique(array_merge(
                User::query()
                    ->where('agent_id', $user->id)
                    ->where('type', UserType::Player->value)
                    ->pluck('id')
                    ->toArray(),
                $user->agent_id ? [$user->agent_id] : []
            )),
            UserType::Player => $user->agent_id ? [$user->agent_id] : [],
            UserType::SystemWallet => User::query()
                ->where('type', UserType::Owner->value)
                ->pluck('id')
                ->toArray(),
        };
    }

    public function transferLog($id)
    {
        abort_if(
            Gate::denies('make_transfer') || ! $this->ifChildOfParent(request()->user()->id, $id),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden | You cannot access this page because you do not have permission'
        );

        $agent = $this->getAgent() ?? Auth::user();

        $transferLogs = TransferLog::with(['fromUser', 'toUser'])
            ->where(function ($q) use ($agent, $id) {
                $q->where('from_user_id', $agent->id)
                    ->where('to_user_id', $id);
            })
            ->orWhere(function ($q) use ($agent, $id) {
                $q->where('from_user_id', $id)
                    ->where('to_user_id', $agent->id);
            })
            ->orderByDesc('id')
            ->paginate();

        return view('admin.trans_log.detail', compact('transferLogs'));
    }

    private function isExistingAgent($userId)
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $userType = UserType::from((int) $user->type);

        return match ($userType) {
            UserType::Player => $user->agent,
            default => null,
        };
    }

    private function getAgent()
    {
        return $this->isExistingAgent(Auth::id());
    }

    private function getAgentOrCurrentUser(): User
    {
        $user = Auth::user();

        return $this->findAgent($user->id) ?? $user;
    }

    private function findAgent(int $userId): ?User
    {
        return $this->isExistingAgent($userId);
    }
}
