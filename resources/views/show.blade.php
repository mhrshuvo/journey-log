@extends('journeylog::layout')

@php use Carbon\Carbon; @endphp

@section('title', 'Journey · ' . $journeyId)

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.5rem;">
        <div>
            <h2 style="margin-bottom:.25rem;">
                Journey
                <code style="font-size:.9rem;background:var(--primary-light);padding:.15rem .45rem;border-radius:.25rem;color:var(--primary);">{{ $journeyId }}</code>
            </h2>
            @if($folder)
                <span class="badge badge-primary">{{ $folder }}</span>
            @endif
            <span class="text-muted text-sm" style="margin-left:.25rem;">{{ count($entries) }} {{ Str::plural('entry', count($entries)) }}</span>
        </div>
        <a href="{{ route('journeylog.index', ['folder' => $folder]) }}" class="btn btn-outline">&larr; Back to Sessions</a>
    </div>

    @if(empty($entries))
        <div class="card empty-state">
            <p>This journey log is empty.</p>
        </div>
    @else
        <div class="timeline">
            @foreach($entries as $i => $entry)
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-card">
                        <div class="timeline-time">
                            @php
                                $dt = isset($entry['datetime']) ? Carbon::parse($entry['datetime']) : null;
                            @endphp
                            #{{ $i + 1 }} &middot; {{ $dt ? $dt->format('M d, Y h:i:s A') : '—' }}
                            @if($dt)
                                <span style="margin-left:.35rem;opacity:.6;">({{ $dt->diffForHumans() }})</span>
                            @endif
                        </div>
                        <div class="timeline-msg">{{ $entry['message'] ?? '(no message)' }}</div>

                        @if(!empty($entry['level_name']) || !empty($entry['channel']))
                            <div class="timeline-meta">
                                @if(!empty($entry['level_name']))
                                    <span class="badge badge-muted">{{ $entry['level_name'] }}</span>
                                @endif
                                @if(!empty($entry['channel']))
                                    <span class="badge badge-primary">{{ $entry['channel'] }}</span>
                                @endif
                            </div>
                        @endif

                        @if(!empty($entry['context']))
                            <div class="timeline-context">{{ json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
