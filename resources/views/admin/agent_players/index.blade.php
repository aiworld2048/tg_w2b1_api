@extends('layouts.master')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>My Players</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active">Players</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between flex-wrap gap-2">
                <h3 class="card-title mb-0">Player Overview</h3>
                @can('create_player')
                    <a href="{{ route('admin.agent.players.create') }}" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> Create Player
                    </a>
                @endcan
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="text-center">
                        <tr>
                            <th>#</th>
                            <th>Player ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Total Spin</th>
                            <th class="text-end">Total Bet</th>
                            <th class="text-end">Total Payout</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $index => $user)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td class="text-center">{{ $user->user_name }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->phone ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $user->status ? 'success' : 'danger' }}">
                                        {{ $user->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format((float) $user->balance, 2) }}</td>
                                <td class="text-end">{{ number_format($user->total_spin) }}</td>
                                <td class="text-end">{{ number_format($user->total_bet_amount, 2) }}</td>
                                <td class="text-end">{{ number_format($user->total_payout_amount, 2) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                                        @can('deposit')
                                            <a href="{{ route('admin.agent.players.getCashIn', $user->id) }}" class="btn btn-sm btn-info text-white">
                                                <i class="fas fa-plus"></i> Deposit
                                            </a>
                                            <a href="{{ route('admin.agent.players.getCashOut', $user->id) }}" class="btn btn-sm btn-info text-white">
                                                <i class="fas fa-minus"></i> Withdraw
                                            </a>
                                        @endcan
                                        <a href="{{ route('admin.agent.players.logs', $user->id) }}" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-clipboard-list"></i> Logs
                                        </a>
                                        <a href="{{ route('admin.agent.players.report', $user->id) }}" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-chart-line"></i> Report
                                        </a>
                                        @can('edit_player')
                                            <a href="{{ route('admin.agent.players.edit', $user->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        @endcan
                                        @can('ban_player')
                                            <form action="{{ route('admin.agent.players.ban', $user->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm {{ $user->status ? 'btn-outline-success' : 'btn-outline-danger' }}">
                                                    <i class="fas fa-user-slash"></i> {{ $user->status ? 'Active' : 'Inactive' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('edit_player')
                                            <form action="{{ route('admin.agent.players.destroy', $user->id) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this player?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No players found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

