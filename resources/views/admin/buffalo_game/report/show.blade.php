@extends('layouts.master')

@section('title', 'Buffalo Game Detail Report')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Buffalo Game Detail Report - {{ $targetUser->user_name }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.buffalo-report.index') }}">Buffalo Game Report</a></li>
                        <li class="breadcrumb-item active">Detail Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Back Button and User Info -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="{{ route('admin.buffalo-report.index', ['from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                            class="btn btn-secondary">
                            <i class="bx bx-arrow-back"></i> Back to Report
                        </a>
                        <div class="text-end">
                            <h5 class="mb-1">{{ $targetUser->user_name }}</h5>
                            <p class="text-muted mb-0">{{ $targetUser->name }}</p>
                            <small class="text-muted">{{ ucfirst($type) }} Report</small>
                        </div>
                    </div>

                    <!-- Date Range Filter -->
                    <form method="GET" action="{{ route('admin.buffalo-report.show', $targetUser->id) }}" class="mb-4">
                        <input type="hidden" name="type" value="{{ $type }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="from_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="from_date" name="from_date" 
                                    value="{{ $fromDate }}">
                            </div>
                            <div class="col-md-4">
                                <label for="to_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="to_date" name="to_date" 
                                    value="{{ $toDate }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-search-alt"></i> Filter
                                    </button>
                                </div>
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
                                            <h4 class="mb-0">{{ number_format($summary['total_bets']) }}</h4>
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
                                            <h4 class="mb-0">{{ number_format($summary['total_bet_amount'], 2) }}</h4>
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
                                            <h4 class="mb-0">{{ number_format($summary['total_win_amount'], 2) }}</h4>
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
                                            <h4 class="mb-0 {{ $summary['net_profit_loss'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($summary['net_profit_loss'], 2) }}
                                            </h4>
                                        </div>
                                        <div class="flex-shrink-0 align-self-center">
                                            <div class="mini-stat-icon avatar-sm rounded-circle {{ $summary['net_profit_loss'] >= 0 ? 'bg-success' : 'bg-danger' }}">
                                                <span class="avatar-title">
                                                    <i class="bx {{ $summary['net_profit_loss'] >= 0 ? 'bx-trending-up' : 'bx-trending-down' }} font-size-24"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Bet Table -->
                    <h5 class="mb-3">Bet History</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date & Time</th>
                                    <th>Member Account</th>
                                    <th>Game ID</th>
                                    <th>Game Name</th>
                                    <th>Before Balance</th>
                                    <th>Bet Amount</th>
                                    <th>Win Amount</th>
                                    <th>After Balance</th>
                                    <th>Net Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bets as $index => $bet)
                                <tr>
                                    <td>{{ $bets->firstItem() + $index }}</td>
                                    <td>
                                        <div>{{ $bet->created_at->format('Y-m-d') }}</div>
                                        <small class="text-muted">{{ $bet->created_at->format('H:i:s') }}</small>
                                    </td>
                                    <td>{{ $bet->member_account }}</td>
                                    <td><code>{{ $bet->buffalo_game_id }}</code></td>
                                    <td>{{ $bet->game_name ?? 'N/A' }}</td>
                                    <td>{{ number_format($bet->before_balance, 2) }}</td>
                                    <td class="text-danger">{{ number_format($bet->bet_amount, 2) }}</td>
                                    <td class="text-success">{{ number_format($bet->win_amount, 2) }}</td>
                                    <td>{{ number_format($bet->balance, 2) }}</td>
                                    <td class="{{ $bet->net_amount >= 0 ? 'text-success' : 'text-danger' }}">
                                        <strong>{{ number_format($bet->net_amount, 2) }}</strong>
                                    </td>
                                    <td>
                                        @if($bet->status === 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($bet->status === 'pending')
                                            <span class="badge bg-warning">Pending</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($bet->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="bx bx-info-circle font-size-24 text-muted"></i>
                                        <p class="text-muted mb-0 mt-2">No bets found for this period</p>
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
                                Showing {{ $bets->firstItem() ?? 0 }} to {{ $bets->lastItem() ?? 0 }} of {{ $bets->total() }} entries
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-7">
                            <div class="dataTables_paginate">
                                {{ $bets->appends(request()->query())->links() }}
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
    // Auto-submit form when date changes
    document.querySelectorAll('#from_date, #to_date').forEach(function(element) {
        element.addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>
@endsection

