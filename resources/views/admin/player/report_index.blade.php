@extends('layouts.master')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Player Report - {{ $player->user_name }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.agent.players.index') }}">Players</a></li>
                    <li class="breadcrumb-item active">Report</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">Bet History</h3>
                <form class="d-flex align-items-end gap-2" method="GET" action="{{ route('admin.agent.players.report', $player->id) }}">
                    <div>
                        <label for="start_date" class="form-label mb-0">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control"
                               value="{{ request('start_date') ?? now()->toDateString() }}">
                    </div>
                    <div>
                        <label for="end_date" class="form-label mb-0">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control"
                               value="{{ request('end_date') ?? now()->toDateString() }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="text-center">
                        <tr>
                            <th>#</th>
                            <th>Wager Code</th>
                            <th>Game</th>
                            <th class="text-end">Bet Amount</th>
                            <th class="text-end">Payout Amount</th>
                            <th>Status</th>
                            <th>Placed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportDetail as $index => $row)
                            <tr>
                                <td class="text-center">{{ $reportDetail->firstItem() + $index }}</td>
                                <td>{{ $row->wager_code }}</td>
                                <td>{{ $row->game_code ?? '-' }}</td>
                                <td class="text-end">{{ number_format($row->bet_amount, 2) }}</td>
                                <td class="text-end">{{ number_format($row->prize_amount, 2) }}</td>
                                <td class="text-center">{{ $row->wager_status }}</td>
                                <td>{{ $row->created_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No bet history found for the selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <strong>Total Bet:</strong> {{ number_format($total['total_bet_amt'], 2) }} |
                    <strong>Total Payout:</strong> {{ number_format($total['total_payout_amt'], 2) }} |
                    <strong>Net:</strong> {{ number_format($total['total_net_win'], 2) }}
                </div>
                {{ $reportDetail->links() }}
            </div>
        </div>
    </div>
</section>
@endsection

