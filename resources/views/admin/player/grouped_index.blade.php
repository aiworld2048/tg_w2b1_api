@extends('layouts.master')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Player Lists by Agent</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active">Player Lists</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Agent ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th class="text-end">Player Count</th>
                                <th class="text-end">Player Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agents as $index => $agent)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">{{ $agent->user_name }}</td>
                                    <td>{{ $agent->name }}</td>
                                    <td>{{ $agent->phone ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($agent->player_count) }}</td>
                                    <td class="text-end">{{ number_format((float) $agent->player_balance_sum, 2) }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('players.grouped.show', $agent->id) }}" class="btn btn-sm btn-primary">
                                            View Players
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No agents found.</td>
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

