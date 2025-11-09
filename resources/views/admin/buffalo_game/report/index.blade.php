@extends('layouts.master')

@section('title', 'Buffalo Game Report')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Buffalo Game Report</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Buffalo Game Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('admin.buffalo-report.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="from_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="from_date" name="from_date" 
                                    value="{{ $fromDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="to_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="to_date" name="to_date" 
                                    value="{{ $toDate }}">
                            </div>
                            
                            @if($viewType === 'agent' && count($agents) > 0)
                            <div class="col-md-3">
                                <label for="agent_id" class="form-label">Agent</label>
                                <select class="form-select" id="agent_id" name="agent_id">
                                    <option value="">All Agents</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ $agentId == $agent->id ? 'selected' : '' }}>
                                            {{ $agent->user_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            <div class="col-md-3">
                                <label for="player_id" class="form-label">Player</label>
                                <select class="form-select" id="player_id" name="player_id">
                                    <option value="">All Players</option>
                                    @foreach($players as $player)
                                        <option value="{{ $player->id }}" {{ $playerId == $player->id ? 'selected' : '' }}>
                                            {{ $player->user_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                    placeholder="Member account or Game ID" value="{{ $searchTerm }}">
                            </div>

                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search-alt"></i> Filter
                                </button>
                                <a href="{{ route('admin.buffalo-report.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-reset"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card mini-stats-wid">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <p class="text-muted fw-medium">Total Bets</p>
                                            <h4 class="mb-0">{{ number_format($reports->sum('total_bets')) }}</h4>
                                        </div>
                                        <div class="flex-shrink-0 align-self-center">
                                            <div class="mini-stat-icon avatar-sm rounded-circle bg-primary">
                                                <span class="avatar-title">
                                                    <i class="bx bx-game font-size-24"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card mini-stats-wid">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <p class="text-muted fw-medium">Total Bet Amount</p>
                                            <h4 class="mb-0">{{ number_format($reports->sum('total_bet_amount'), 2) }}</h4>
                                        </div>
                                        <div class="flex-shrink-0 align-self-center">
                                            <div class="mini-stat-icon avatar-sm rounded-circle bg-warning">
                                                <span class="avatar-title">
                                                    <i class="bx bx-money font-size-24"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card mini-stats-wid">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <p class="text-muted fw-medium">Total Win Amount</p>
                                            <h4 class="mb-0">{{ number_format($reports->sum('total_win_amount'), 2) }}</h4>
                                        </div>
                                        <div class="flex-shrink-0 align-self-center">
                                            <div class="mini-stat-icon avatar-sm rounded-circle bg-success">
                                                <span class="avatar-title">
                                                    <i class="bx bx-trophy font-size-24"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card mini-stats-wid">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <p class="text-muted fw-medium">Net Profit/Loss</p>
                                            <h4 class="mb-0 {{ $reports->sum('net_profit_loss') >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($reports->sum('net_profit_loss'), 2) }}
                                            </h4>
                                        </div>
                                        <div class="flex-shrink-0 align-self-center">
                                            <div class="mini-stat-icon avatar-sm rounded-circle {{ $reports->sum('net_profit_loss') >= 0 ? 'bg-success' : 'bg-danger' }}">
                                                <span class="avatar-title">
                                                    <i class="bx {{ $reports->sum('net_profit_loss') >= 0 ? 'bx-trending-up' : 'bx-trending-down' }} font-size-24"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    @if($viewType === 'agent')
                                        <th>Agent Name</th>
                                        <th>Total Players</th>
                                    @else
                                        <th>Player Name</th>
                                        <th>Agent Name</th>
                                    @endif
                                    <th>Total Bets</th>
                                    <th>Total Bet Amount</th>
                                    <th>Total Win Amount</th>
                                    <th>Net Profit/Loss</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $index => $report)
                                <tr>
                                    <td>{{ $reports->firstItem() + $index }}</td>
                                    @if($viewType === 'agent')
                                        <td>
                                            <strong>{{ $report->agent->user_name ?? 'N/A' }}</strong>
                                            @if($report->agent)
                                                <br><small class="text-muted">{{ $report->agent->name }}</small>
                                            @endif
                                        </td>
                                        <td>{{ number_format($report->total_players) }}</td>
                                    @else
                                        <td>
                                            <strong>{{ $report->player->user_name ?? 'N/A' }}</strong>
                                            @if($report->player)
                                                <br><small class="text-muted">{{ $report->player->name }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $report->agent->user_name ?? 'N/A' }}</td>
                                    @endif
                                    <td>{{ number_format($report->total_bets) }}</td>
                                    <td>{{ number_format($report->total_bet_amount, 2) }}</td>
                                    <td>{{ number_format($report->total_win_amount, 2) }}</td>
                                    <td class="{{ $report->net_profit_loss >= 0 ? 'text-success' : 'text-danger' }}">
                                        <strong>{{ number_format($report->net_profit_loss, 2) }}</strong>
                                    </td>
                                    <td>
                                        @if($viewType === 'agent')
                                            <a href="{{ route('admin.buffalo-report.show', ['id' => $report->player_agent_id, 'type' => 'agent', 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                                                class="btn btn-sm btn-info">
                                                <i class="bx bx-show"></i> View Details
                                            </a>
                                        @else
                                            <a href="{{ route('admin.buffalo-report.show', ['id' => $report->player_id, 'type' => 'player', 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                                                class="btn btn-sm btn-info">
                                                <i class="bx bx-show"></i> View Details
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bx bx-info-circle font-size-24 text-muted"></i>
                                        <p class="text-muted mb-0 mt-2">No data available for the selected period</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="row mt-4">
                        <div class="col-sm-12 col-md-5">
                            <div class="dataTables_info">
                                Showing {{ $reports->firstItem() ?? 0 }} to {{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} entries
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-7">
                            <div class="dataTables_paginate">
                                {{ $reports->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Auto-submit form when date or select changes
    document.querySelectorAll('#from_date, #to_date, #agent_id, #player_id').forEach(function(element) {
        element.addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>
@endsection

