<?php

namespace App\Http\Controllers\Admin\BuffaloGame;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\LogBuffaloBet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuffaloReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get filter parameters - default to today's data
        $fromDate = $request->input('from_date', now()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));
        $searchTerm = $request->input('search');
        $agentId = $request->input('agent_id');
        $playerId = $request->input('player_id');

        // Base query
        $query = LogBuffaloBet::with(['player', 'agent'])
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

        // Role-based filtering
        if ($user->type == UserType::Owner || $user->type == UserType::Master) {
            // Owner/Master can see all data, grouped by agent
            if ($agentId) {
                $query->where('player_agent_id', $agentId);
            }
            if ($playerId) {
                $query->where('player_id', $playerId);
            }
        } elseif ($user->type == UserType::Agent) {
            // Agent can see their own players only
            $playerIds = $user->getAllDescendantPlayers()->pluck('id')->toArray();
            $query->whereIn('player_id', $playerIds);
            
            if ($playerId && in_array($playerId, $playerIds)) {
                $query->where('player_id', $playerId);
            }
        } elseif ($user->type == UserType::SubAgent) {
            // SubAgent can see their own players only
            $playerIds = $user->children()->where('type', UserType::Player)->pluck('id')->toArray();
            $query->whereIn('player_id', $playerIds);
            
            if ($playerId && in_array($playerId, $playerIds)) {
                $query->where('player_id', $playerId);
            }
        }

        // Search filter
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('member_account', 'like', "%{$searchTerm}%")
                    ->orWhere('buffalo_game_id', 'like', "%{$searchTerm}%");
            });
        }

        // Get aggregated data based on user role
        if ($user->type == UserType::Owner || $user->type == UserType::Master) {
            // Group by agent for Owner/Master
            $reports = $this->getAgentGroupedReport($query, $request);
            $viewType = 'agent';
        } else {
            // Group by player for Agent/SubAgent
            $reports = $this->getPlayerGroupedReport($query, $request);
            $viewType = 'player';
        }

        // Get agents list for filter (Owner/Master only)
        $agents = [];
        if ($user->type == UserType::Owner || $user->type == UserType::Master) {
            $agents = User::where('type', UserType::Agent)
                ->orWhere('type', UserType::SubAgent)
                ->orderBy('user_name')
                ->get();
        }

        // Get players list for filter
        $players = [];
        if ($user->type == UserType::Owner || $user->type == UserType::Master) {
            $players = User::where('type', UserType::Player)->orderBy('user_name')->get();
        } elseif ($user->type == UserType::Agent) {
            $players = $user->getAllDescendantPlayers();
        } elseif ($user->type == UserType::SubAgent) {
            $players = $user->children()->where('type', UserType::Player)->get();
        }

        return view('admin.buffalo_game.report.index', compact(
            'reports',
            'viewType',
            'fromDate',
            'toDate',
            'searchTerm',
            'agents',
            'players',
            'agentId',
            'playerId'
        ));
    }

    /**
     * Get report data grouped by agent (for Owner/Master)
     */
    private function getAgentGroupedReport($query, $request)
    {
        $reports = $query->clone()
            ->select(
                'player_agent_id',
                DB::raw('COUNT(*) as total_bets'),
                DB::raw('COUNT(DISTINCT player_id) as total_players'),
                DB::raw('SUM(bet_amount) as total_bet_amount'),
                DB::raw('SUM(win_amount) as total_win_amount'),
                DB::raw('SUM(win_amount - bet_amount) as net_profit_loss')
            )
            ->groupBy('player_agent_id')
            ->paginate(20);

        // Attach agent details
        $reports->getCollection()->transform(function ($report) {
            $report->agent = User::find($report->player_agent_id);
            return $report;
        });

        return $reports;
    }

    /**
     * Get report data grouped by player (for Agent/SubAgent)
     */
    private function getPlayerGroupedReport($query, $request)
    {
        $reports = $query->clone()
            ->select(
                'player_id',
                'player_agent_id',
                DB::raw('COUNT(*) as total_bets'),
                DB::raw('SUM(bet_amount) as total_bet_amount'),
                DB::raw('SUM(win_amount) as total_win_amount'),
                DB::raw('SUM(win_amount - bet_amount) as net_profit_loss')
            )
            ->groupBy('player_id', 'player_agent_id')
            ->paginate(20);

        // Attach player and agent details
        $reports->getCollection()->transform(function ($report) {
            $report->player = User::find($report->player_id);
            $report->agent = User::find($report->player_agent_id);
            return $report;
        });

        return $reports;
    }

    /**
     * Show detailed report for a specific agent or player
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $type = $request->input('type', 'player'); // 'agent' or 'player'
        
        // Default to today's data
        $fromDate = $request->input('from_date', now()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        // Base query
        $query = LogBuffaloBet::with(['player', 'agent'])
            ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);

        if ($type === 'agent') {
            // Show all players under this agent
            $query->where('player_agent_id', $id);
            $targetUser = User::findOrFail($id);
            
            // Check permission
            if (!in_array($user->type->value, [UserType::Owner->value, UserType::Master->value])) {
                abort(403, 'Unauthorized access');
            }
        } else {
            // Show specific player's bets
            $query->where('player_id', $id);
            $targetUser = User::findOrFail($id);
            
            // Check permission
            if ($user->type == UserType::Agent) {
                $allowedPlayerIds = $user->getAllDescendantPlayers()->pluck('id')->toArray();
                if (!in_array($id, $allowedPlayerIds)) {
                    abort(403, 'Unauthorized access');
                }
            } elseif ($user->type == UserType::SubAgent) {
                $allowedPlayerIds = $user->children()->where('type', UserType::Player)->pluck('id')->toArray();
                if (!in_array($id, $allowedPlayerIds)) {
                    abort(403, 'Unauthorized access');
                }
            }
        }

        // Get detailed bet logs
        $bets = $query->orderBy('created_at', 'desc')->paginate(50);

        // Calculate summary
        $summary = [
            'total_bets' => $bets->total(),
            'total_bet_amount' => $query->clone()->sum('bet_amount'),
            'total_win_amount' => $query->clone()->sum('win_amount'),
            'net_profit_loss' => $query->clone()->sum(DB::raw('win_amount - bet_amount')),
        ];

        return view('admin.buffalo_game.report.show', compact(
            'bets',
            'targetUser',
            'type',
            'fromDate',
            'toDate',
            'summary'
        ));
    }
}

