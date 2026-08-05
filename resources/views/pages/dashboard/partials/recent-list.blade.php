@php
    $title = $title ?? 'Recent';
    $rows = $rows ?? [];
    $empty = $empty ?? 'No recent records.';
    $col = $col ?? 'col-12 col-lg-6';
@endphp

<div class="{{ $col }}">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header border-0">
            <h5 class="mb-0">{{ $title }}</h5>
        </div>
        <div class="card-body pt-0">
            @if (count($rows) === 0)
                <p class="text-muted mb-0">{{ $empty }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 dashboard-recent-table">
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td class="{{ $loop->first ? 'fw-semibold' : 'text-muted' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
