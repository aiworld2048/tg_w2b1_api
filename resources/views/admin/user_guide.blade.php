@extends('layouts.master')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Admin User Guide</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">User Guide</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body markdown-body">
                    {!! $content !!}
                </div>
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <style>
        .markdown-body h1,
        .markdown-body h2,
        .markdown-body h3,
        .markdown-body h4 {
            font-weight: 600;
            margin-top: 1.5rem;
        }

        .markdown-body ul {
            padding-left: 1.25rem;
        }

        .markdown-body pre,
        .markdown-body code {
            background-color: #f6f8fa;
            border-radius: 4px;
            padding: 0.2rem 0.4rem;
        }
    </style>
@endpush

