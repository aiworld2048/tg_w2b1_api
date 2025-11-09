@extends('layouts.master')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>{{ $agent->name }} &mdash; Players</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.players.grouped') }}">Player Lists</a></li>
                    <li class="breadcrumb-item active">{{ $agent->name }}</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="card-title mb-0">Agent Information</h3>
                    <p class="mb-0 small text-muted">Agent ID: {{ $agent->user_name }}</p>
                </div>
                <div class="d-flex gap-2">
                    @can('create_player')
                        <a href="{{ route('admin.players.create', ['agent_id' => $agent->id]) }}" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> Create Player
                        </a>
                    @endcan
                    <a href="{{ route('admin.players.grouped') }}" class="btn btn-outline-secondary">
                        &larr; Back to Agents
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Player ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($players as $index => $player)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">{{ $player->user_name }}</td>
                                    <td>{{ $player->name }}</td>
                                    <td>{{ $player->phone ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $player->status ? 'success' : 'danger' }}">
                                            {{ $player->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $player->balance, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No players found for this agent.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

