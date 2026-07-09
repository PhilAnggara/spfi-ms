<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <h5 class="card-title mb-0">{{ $selectedCurrency }} Rate History</h5>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-sm-6">
            <label for="sort_by" class="form-label mb-1">Sort By</label>
            <select id="sort_by" name="sort_by" class="form-select" data-exchange-rate-sort>
                <option value="effective_date" @selected($historySort['sort_by'] === 'effective_date')>Effective Date</option>
                <option value="rate_to_idr" @selected($historySort['sort_by'] === 'rate_to_idr')>Exchange Rate</option>
                <option value="created_at" @selected($historySort['sort_by'] === 'created_at')>Recorded At</option>
            </select>
        </div>
        <div class="col-12 col-sm-6">
            <label for="sort_direction" class="form-label mb-1">Order</label>
            <select id="sort_direction" name="sort_direction" class="form-select" data-exchange-rate-sort>
                <option value="desc" @selected($historySort['sort_direction'] === 'desc')>Descending</option>
                <option value="asc" @selected($historySort['sort_direction'] === 'asc')>Ascending</option>
            </select>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped text-center text-nowrap mb-0">
        <thead>
            <tr>
                <th>
                    <button
                        type="button"
                        class="btn btn-link text-dark text-decoration-none p-0 border-0"
                        data-exchange-rate-sort-column="effective_date">
                        Effective Date
                        @if ($historySort['sort_by'] === 'effective_date')
                            <i class="fa-solid fa-sort-{{ $historySort['sort_direction'] === 'asc' ? 'up' : 'down' }} ms-1"></i>
                        @endif
                    </button>
                </th>
                <th>
                    <button
                        type="button"
                        class="btn btn-link text-dark text-decoration-none p-0 border-0"
                        data-exchange-rate-sort-column="rate_to_idr">
                        Rate (1 {{ $selectedCurrency }})
                        @if ($historySort['sort_by'] === 'rate_to_idr')
                            <i class="fa-solid fa-sort-{{ $historySort['sort_direction'] === 'asc' ? 'up' : 'down' }} ms-1"></i>
                        @endif
                    </button>
                </th>
                <th>Notes</th>
                <th>Updated By</th>
                <th>
                    <button
                        type="button"
                        class="btn btn-link text-dark text-decoration-none p-0 border-0"
                        data-exchange-rate-sort-column="created_at">
                        Recorded At
                        @if ($historySort['sort_by'] === 'created_at')
                            <i class="fa-solid fa-sort-{{ $historySort['sort_direction'] === 'asc' ? 'up' : 'down' }} ms-1"></i>
                        @endif
                    </button>
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse ($history as $rate)
                <tr>
                    <td>{{ $rate->effective_date?->format('d M Y') ?? '-' }}</td>
                    <td>{{ number_format((float) $rate->rate_to_idr, 4, '.', ',') }}</td>
                    <td>{{ $rate->notes ?: '-' }}</td>
                    <td>{{ $rate->updatedBy?->name ?? $rate->createdBy?->name ?? '-' }}</td>
                    <td>{{ $rate->created_at?->format('d M Y H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-muted py-4">No {{ $selectedCurrency }} exchange rate history yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($history->hasPages())
    <div class="mt-3" data-exchange-rate-pagination>
        {{ $history->links() }}
    </div>
@endif
