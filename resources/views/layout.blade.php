<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Journey Log')</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --text: #1e293b;
            --muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .navbar {
            background: var(--primary);
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }

        .navbar h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .navbar a {
            color: rgba(255,255,255,.85);
            text-decoration: none;
            font-size: .875rem;
        }

        .navbar a:hover { color: #fff; }

        .container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: .75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }

        .badge {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 600;
        }

        .badge-primary { background: var(--primary-light); color: var(--primary); }
        .badge-muted   { background: #f1f5f9; color: var(--muted); }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        th {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            background: #f8fafc;
        }

        tr:hover td { background: #fafbfd; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .5rem 1rem;
            border: none;
            border-radius: .375rem;
            font-size: .8125rem;
            font-weight: 500;
            text-decoration: none;
            transition: all .15s;
            cursor: pointer;
            white-space: nowrap;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border: 1px solid var(--primary);
        }

        .btn-primary:hover { 
            background: #4338ca; 
            border-color: #4338ca;
        }

        .btn-success {
            background: #059669;
            color: #fff;
            border: 1px solid #059669;
        }

        .btn-success:hover { 
            background: #047857; 
            border-color: #047857;
        }

        .btn-danger {
            background: #dc2626;
            color: #fff;
            border: 1px solid #dc2626;
        }

        .btn-danger:hover { 
            background: #b91c1c; 
            border-color: #b91c1c;
        }

        .btn-outline {
            border: 1px solid var(--border);
            color: var(--text);
            background: var(--card);
        }

        .btn-outline:hover { background: #f1f5f9; }

        .filter-bar {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            align-items: center;
        }

        .filter-bar select {
            padding: .45rem .75rem;
            border: 1px solid var(--border);
            border-radius: .375rem;
            font-size: .875rem;
            background: var(--card);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: .4;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2.5rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: .875rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-dot {
            position: absolute;
            left: -1.75rem;
            top: .45rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid var(--card);
            box-shadow: 0 0 0 2px var(--primary);
        }

        .timeline-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: .5rem;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }

        .timeline-time {
            font-size: .75rem;
            color: var(--muted);
            margin-bottom: .25rem;
        }

        .timeline-msg {
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .timeline-meta {
            font-size: .8125rem;
            color: var(--muted);
        }

        .timeline-context {
            margin-top: .5rem;
            padding: .75rem;
            background: #f8fafc;
            border-radius: .375rem;
            font-size: .8125rem;
            font-family: 'Fira Code', 'Cascadia Code', monospace;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 200px;
            overflow-y: auto;
        }

        .text-muted { color: var(--muted); }
        .text-sm    { font-size: .875rem; }
        .mt-1       { margin-top: .5rem; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Journey Log</h1>
        <a href="{{ route('journeylog.index') }}">Sessions</a>
    </nav>

    <div class="container">
        @yield('content')
    </div>
</body>
</html>
