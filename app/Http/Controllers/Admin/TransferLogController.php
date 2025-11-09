<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\TransferLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TransferLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $relatedIds = $this->getDirectlyRelatedUserIds($user);

        $baseQuery = TransferLog::where(function ($q) use ($user, $relatedIds) {
            $q->where(function ($q2) use ($user, $relatedIds) {
                $q2->where('from_user_id', $user->id)
                    ->whereIn('to_user_id', $relatedIds);
            })
                ->orWhere(function ($q2) use ($user, $relatedIds) {
                    $q2->where('to_user_id', $user->id)
                        ->whereIn('from_user_id', $relatedIds);
                });
        });

        // All-time totals (unfiltered)
        $allTimeTotalDeposit = (clone $baseQuery)->where('type', 'top_up')->sum('amount');
        $allTimeTotalWithdraw = (clone $baseQuery)->where('type', 'withdraw')->sum('amount');
        $allTimeProfit = $allTimeTotalDeposit - $allTimeTotalWithdraw;

        // Query for the table, with all filters applied
        $tableQuery = (clone $baseQuery)->with(['fromUser', 'toUser']);
        if ($request->filled('type')) {
            $tableQuery->where('type', $request->type);
        }

        // Query for filtered info boxes, which is only filtered by date
        $dateFilteredQuery = (clone $baseQuery);
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = $request->date_from.' 00:00:00';
            $to = $request->date_to.' 23:59:59';
            $tableQuery->whereBetween('created_at', [$from, $to]);
            $dateFilteredQuery->whereBetween('created_at', [$from, $to]);
        }

        // Daily totals based on date-filtered query
        $dailyTotalDeposit = (clone $dateFilteredQuery)->where('type', 'top_up')->sum('amount');
        $dailyTotalWithdraw = (clone $dateFilteredQuery)->where('type', 'withdraw')->sum('amount');
        $dailyProfit = $dailyTotalDeposit - $dailyTotalWithdraw;

        $transferLogs = $tableQuery->latest()->paginate(20);

        return view('admin.transfer_logs.index', compact(
            'transferLogs',
            'dailyTotalDeposit',
            'dailyTotalWithdraw',
            'allTimeTotalDeposit',
            'allTimeTotalWithdraw',
            'dailyProfit',
            'allTimeProfit'
        ));
    }

    /**
     * Get only directly related user IDs according to the hierarchy:
     * Owner → Agent → SubAgent, Agent→Player, SubAgent→Player
     */
    private function getDirectlyRelatedUserIds(User $user): array
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
            default => [],
        };
    }

    public function PlayertransferLog($relatedUserId)
    {
        $user = Auth::user();
        $relatedUser = User::findOrFail($relatedUserId);

        $allowedIds = $this->getDirectlyRelatedUserIds($user);
        if (! in_array($relatedUser->id, $allowedIds, true)) {
            abort(403, 'Unauthorized to view this transfer log.');
        }

        $transferLogs = TransferLog::with(['fromUser', 'toUser'])
            ->where(function ($q) use ($user, $relatedUser) {
                $q->where('from_user_id', $user->id)
                    ->where('to_user_id', $relatedUser->id);
            })
            ->orWhere(function ($q) use ($user, $relatedUser) {
                $q->where('from_user_id', $relatedUser->id)
                    ->where('to_user_id', $user->id);
            })
            ->latest()
            ->get();

        return view('admin.transfer_logs.player_transfer_log_index', compact('transferLogs', 'relatedUser'));
    }
}
