@extends('journeylog::layout')

@php use Carbon\Carbon; @endphp

@section('title', 'Journey Sessions')

@section('content')
    <h2 style="margin-bottom:.5rem;">Sessions</h2>
    <p class="text-muted text-sm" style="margin-bottom:1.25rem;">Browse user journey logs recorded by JourneyLog.</p>

    <div class="filter-bar">
        <form method="GET" action="{{ route('journeylog.index') }}" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            @if(count($folders))
                <div style="display:flex;gap:.5rem;align-items:center;">
                    <label for="folder" class="text-sm">Folder:</label>
                    <select name="folder" id="folder" onchange="this.form.submit()">
                        <option value="">All (root)</option>
                        @foreach($folders as $f)
                            <option value="{{ $f }}" {{ $folder === $f ? 'selected' : '' }}>{{ $f }}</option>
                        @endforeach
                    </select>
                    @if($folder)
                        <a href="#" onclick="deleteFolder('{{ $folder }}'); return false;" class="btn btn-danger" title="Delete entire folder and all its journey logs">
                            Delete Folder
                        </a>
                    @endif
                </div>
            @endif
            
            <div style="display:flex;gap:.5rem;align-items:center;">
                <label for="search" class="text-sm">Search Journey ID:</label>
                <input type="text" name="search" id="search" value="{{ $search ?? '' }}" 
                       placeholder="Enter journey ID..." 
                       style="padding:.375rem .75rem;border:1px solid #ddd;border-radius:.25rem;">
                <a href="#" onclick="document.querySelector('form').submit(); return false;" class="btn btn-primary">Search</a>
            </div>
        </form>
        
        @if(!$journeys->isEmpty())
            <div style="margin-top:1rem;">
                <a href="#" id="bulk-delete-btn" class="btn btn-danger" style="display:none;" onclick="bulkDelete(); return false;">Delete Selected</a>
            </div>
        @endif
    </div>

    @if($journeys->isEmpty())
        <div class="card empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125
                         1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25
                         0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125
                         1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0
                         00-9-9z"/>
            </svg>
            <p>No journey logs found{{ $folder ? ' in folder "'.$folder.'"' : '' }}{{ isset($search) && $search ? ' matching "'.$search.'"' : '' }}.</p>
        </div>
    @else
        <div class="card" style="padding:0;overflow:hidden;">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                        </th>
                        <th>Journey ID</th>
                        @if(!$folder)<th>Folder</th>@endif
                        <th>Entries</th>
                        <th>Duration</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($journeys as $j)
                        <tr>
                            <td>
                                <input type="checkbox" class="journey-checkbox" 
                                       value="{{ $j['journey_id'] }}|{{ $j['folder'] ?? '' }}" 
                                       onchange="toggleBulkDeleteButton()">
                            </td>
                            <td>
                                <code style="font-size:.8125rem;">{{ $j['journey_id'] }}</code>
                            </td>
                            @if(!$folder)
                                <td>
                                    @if($j['folder'])
                                        <span class="badge badge-primary">{{ $j['folder'] }}</span>
                                    @else
                                        <span class="badge badge-muted">root</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $j['entry_count'] }}</td>
                            <td class="text-sm text-muted">
                                @php
                                    $first = isset($j['first_datetime']) ? Carbon::parse($j['first_datetime']) : null;
                                    $last  = isset($j['last_datetime'])  ? Carbon::parse($j['last_datetime'])  : null;
                                @endphp
                                @if($first && $last)
                                    {{ $first->format('M d, Y h:i:s A') }}
                                    <span style="opacity:.45;">&rarr;</span>
                                    {{ $last->format('h:i:s A') }}
                                    <br>
                                    <span style="font-size:.75rem;color:var(--primary);font-weight:600;">{{ $first->diff($last)->forHumans(['short' => true, 'parts' => 2]) ?: 'instant' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ number_format($j['file_size'] / 1024, 1) }} KB</td>
                            <td>
                                <div style="display:flex;gap:.5rem;align-items:center;">
                                    <a href="{{ route('journeylog.show', ['journeyId' => $j['journey_id'], 'folder' => $j['folder']]) }}"
                                       class="btn btn-primary">
                                        View Timeline
                                    </a>
                                    <a href="{{ route('journeylog.download', ['journeyId' => $j['journey_id'], 'folder' => $j['folder']]) }}"
                                       class="btn btn-success">
                                        Download
                                    </a>
                                    <a href="#" onclick="deleteJourney('{{ $j['journey_id'] }}', '{{ $j['folder'] ?? '' }}'); return false;"
                                       class="btn btn-danger">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <script>
        // CSRF token for AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.journey-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            toggleBulkDeleteButton();
        }

        function toggleBulkDeleteButton() {
            const checkboxes = document.querySelectorAll('.journey-checkbox:checked');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
            
            if (checkboxes.length > 0) {
                bulkDeleteBtn.style.display = 'inline-block';
                bulkDeleteBtn.textContent = `Delete Selected (${checkboxes.length})`;
            } else {
                bulkDeleteBtn.style.display = 'none';
            }
        }

        function deleteJourney(journeyId, folder) {
            if (!confirm('Are you sure you want to delete this journey log? This action cannot be undone.')) {
                return;
            }

            const url = `/journey-log/delete/${journeyId}?folder=${encodeURIComponent(folder)}`;
            
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the journey log.');
            });
        }

        function deleteFolder(folderName) {
            if (!confirm(`Are you sure you want to delete the entire folder "${folderName}" and all its journey logs? This action cannot be undone.`)) {
                return;
            }

            const url = `/journey-log/delete-folder/${encodeURIComponent(folderName)}`;
            
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Redirect to show all folders after deletion
                    window.location.href = '{{ route("journeylog.index") }}';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the folder.');
            });
        }

        function bulkDelete() {
            const checkboxes = document.querySelectorAll('.journey-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one journey log to delete.');
                return;
            }
            
            const count = checkboxes.length;
            if (!confirm(`Are you sure you want to delete ${count} journey log(s)? This action cannot be undone.`)) {
                return;
            }

            const journeyIds = Array.from(checkboxes).map(cb => cb.value);
            
            fetch('/journey-log/bulk-delete', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    journey_ids: journeyIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the journey logs.');
            });
        }
    </script>
@endsection
