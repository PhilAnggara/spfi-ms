@php
    $docTypeLabels = [
        'RR' => 'Receiving Report',
        'DR' => 'Delivery Receipt',
    ];
    $docTypeLabel = $docTypeLabels[$docType] ?? $docType;
    $inModal = $inModal ?? false;
    $sourceUrl = $sourceUrl ?? null;

    $totalGroupCodes = $transaction->lines->sum(
        fn ($line): float => (float) preg_replace('/[^\d.]/', '', (string) ($line->group_code ?? '')) ?: 0
    );
    $totalAccountCodes = $transaction->lines->sum(
        fn ($line): float => (float) preg_replace('/[^\d.]/', '', (string) ($line->account_code ?? '')) ?: 0
    );
    $headerCostCodeTotal = (float) ($transaction->cost_code_total ?: $totalGroupCodes);
    $headerAcctCodeTotal = (float) ($transaction->acct_code_total ?: $totalAccountCodes);
@endphp

<div class="doc-entry-panel" data-doc-entry-panel>
    <div class="rounded-3 border bg-light bg-opacity-50 p-3 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">{{ $transaction->doc_type }}</span>
                    @if ($sourceUrl)
                        <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="fs-4 fw-semibold text-decoration-none text-body">
                            {{ $transaction->doc_number }}
                            <i class="fa-light fa-arrow-up-right-from-square ms-1 small text-muted"></i>
                        </a>
                    @else
                        <span class="fs-4 fw-semibold">{{ $transaction->doc_number }}</span>
                    @endif
                    @if ($isEncoded)
                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">Encoded</span>
                    @else
                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning">Draft</span>
                    @endif
                </div>
                <div class="text-muted small d-flex flex-wrap gap-2">
                    <span>{{ $docTypeLabel }}</span>
                    @if ($transaction->doc_date)
                        <span>&middot;</span>
                        <span>{{ $transaction->doc_date->format('d M Y') }}</span>
                    @endif
                    @if ($transaction->po_number)
                        <span>&middot;</span>
                        <span>Ref {{ $transaction->po_number }}</span>
                    @endif
                </div>
            </div>
            <div class="text-md-end">
                @if ($transaction->supplier_name)
                    <div class="fw-semibold">{{ $transaction->supplier_name }}</div>
                @endif
                @if ($transaction->supplier_code)
                    <div class="text-muted small font-monospace">{{ $transaction->supplier_code }}</div>
                @endif
                @if ($isEncoded && $transaction->encodedBy)
                    <div class="text-muted small mt-2">
                        <i class="fa-light fa-user-check me-1"></i>
                        {{ $transaction->encodedBy->name }}
                        @if ($transaction->encoded_at)
                            &middot; {{ $transaction->encoded_at->format('d M Y H:i') }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($isEncoded)
        <div class="alert alert-secondary border-0 py-2 mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="fa-light fa-lock"></i>
            <span>This journal is encoded and read-only.</span>
        </div>
    @elseif ($docType === 'DR' && $transaction->lines->isEmpty())
        <div class="alert alert-warning border-0 py-2 mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="fa-light fa-pen-to-square"></i>
            <span>No automatic journal lines yet. Add debit/credit lines manually before encoding.</span>
        </div>
    @endif

    <form method="POST" action="{{ route('accounting.doc-entries.update', $transaction) }}" id="doc-entry-form">
        @csrf
        @method('PUT')

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h6 class="mb-0">Accounting Lines</h6>
                <div class="text-muted small">Cost Center appears only for 4-digit account codes.</div>
            </div>
            @if ($canEdit)
                <button type="button" class="btn btn-primary btn-sm" id="add-line-btn">
                    <i class="fa-light fa-plus me-1"></i>
                    Add Line
                </button>
            @endif
        </div>

        <div class="table-responsive border rounded-3 overflow-hidden shadow-sm">
            <table class="table table-sm table-hover align-middle mb-0" id="doc-entry-lines-table">
                <thead class="table-light sticky-top">
                    <tr class="small text-uppercase text-muted">
                        <th style="width: 14%;">Cost Center</th>
                        <th style="width: 14%;">Account</th>
                        <th>Description</th>
                        <th class="text-end" style="width: 14%;">Debit</th>
                        <th class="text-end" style="width: 14%;">Credit</th>
                        @if ($canEdit)
                            <th style="width: 5%;"></th>
                        @endif
                    </tr>
                </thead>
                <tbody id="doc-entry-lines-body">
                    @forelse ($transaction->lines as $index => $line)
                        @php
                            $displayCostCenter = $line->displayCostCenter();
                        @endphp
                        <tr class="doc-entry-line-row">
                            <td>
                                @if ($canEdit)
                                    <input type="text"
                                           class="form-control form-control-sm group-code-input font-monospace"
                                           name="lines[{{ $index }}][group_code]"
                                           value="{{ $line->group_code }}"
                                           title="Stored as group code; shown as Cost Center for 4-digit accounts">
                                @else
                                    <span class="font-monospace cost-center-display text-muted">{{ $displayCostCenter ?: '—' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($canEdit)
                                    <input type="text" class="form-control form-control-sm account-code-input font-monospace" name="lines[{{ $index }}][account_code]" value="{{ $line->account_code }}">
                                @else
                                    <span class="fw-semibold font-monospace">{{ $line->account_code }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($canEdit)
                                    <input type="text" class="form-control form-control-sm account-description-input" name="lines[{{ $index }}][description]" value="{{ $line->description }}">
                                @else
                                    {{ $line->description ?: '—' }}
                                @endif
                            </td>
                            <td class="text-end font-monospace">
                                @if ($canEdit)
                                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end debit-input" name="lines[{{ $index }}][debit]" value="{{ (float) $line->debit > 0 ? $line->debit : '' }}">
                                @else
                                    {{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2, '.', ',') : '—' }}
                                @endif
                            </td>
                            <td class="text-end font-monospace">
                                @if ($canEdit)
                                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end credit-input" name="lines[{{ $index }}][credit]" value="{{ (float) $line->credit > 0 ? $line->credit : '' }}">
                                @else
                                    {{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2, '.', ',') : '—' }}
                                @endif
                            </td>
                            @if ($canEdit)
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 remove-line-btn" title="Remove line">
                                        <i class="fa-light fa-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        @if ($canEdit)
                            <tr class="doc-entry-line-row">
                                <td><input type="text" class="form-control form-control-sm group-code-input font-monospace" name="lines[0][group_code]" value=""></td>
                                <td><input type="text" class="form-control form-control-sm account-code-input font-monospace" name="lines[0][account_code]" value=""></td>
                                <td><input type="text" class="form-control form-control-sm account-description-input" name="lines[0][description]" value=""></td>
                                <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end debit-input" name="lines[0][debit]" value=""></td>
                                <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end credit-input" name="lines[0][credit]" value=""></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 remove-line-btn" title="Remove line">
                                        <i class="fa-light fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">No accounting lines.</td>
                            </tr>
                        @endif
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-semibold">
                        <td class="font-monospace" id="total-cost-center-codes" title="Cost code total">{{ number_format($headerCostCodeTotal, 0, '.', ',') }}</td>
                        <td class="font-monospace" id="total-account-codes" title="Account code total">{{ number_format($headerAcctCodeTotal, 0, '.', ',') }}</td>
                        <td class="text-end text-muted small">Amounts</td>
                        <td class="text-end font-monospace" id="total-debit-display">{{ number_format((float) $transaction->total_debit, 2, '.', ',') }}</td>
                        <td class="text-end font-monospace" id="total-credit-display">{{ number_format((float) $transaction->total_credit, 2, '.', ',') }}</td>
                        @if ($canEdit)
                            <td></td>
                        @endif
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end fw-semibold">Variance</td>
                        <td colspan="2" id="variance-display" class="text-end font-monospace fw-bold {{ abs((float) $transaction->variance) > 0.0001 ? 'text-danger' : 'text-success' }}">
                            {{ number_format((float) $transaction->variance, 2, '.', ',') }}
                        </td>
                        @if ($canEdit)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="d-flex flex-wrap gap-3 small text-muted mt-2 mb-0">
            <span><span class="fw-semibold">Cost Code Total</span> = sum of Cost Center codes</span>
            <span><span class="fw-semibold">Acct Code Total</span> = sum of Account Codes</span>
        </div>

        @if ($canEdit)
            <div id="variance-alert" class="alert alert-warning border-0 mt-3 mb-0 {{ abs((float) $transaction->variance) > 0.0001 ? '' : 'd-none' }}" role="alert">
                <i class="fa-light fa-scale-unbalanced me-1"></i>
                Debit and credit are not balanced. You can still encode after reviewing the variance.
            </div>
        @endif

        @if ($canEdit)
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                @if ($inModal)
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                @endif
                <button type="submit" class="btn btn-success px-4 icon icon-left">
                    <i class="fa-light fa-floppy-disk"></i>
                    Save &amp; Encode
                </button>
            </div>
        @elseif ($inModal)
            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        @endif
    </form>
</div>

@if ($canEdit)
<script>
(function () {
    const root = document.querySelector('[data-doc-entry-panel]');
    if (!root || root.dataset.bound === '1') {
        return;
    }
    root.dataset.bound = '1';

    const linesBody = root.querySelector('#doc-entry-lines-body');
    const addLineBtn = root.querySelector('#add-line-btn');
    const lookupUrl = @json(route('accounting.doc-entries.account-lookup'));

    if (!linesBody || !addLineBtn) {
        return;
    }

    function formatAmount(value) {
        return Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatCodeTotal(value) {
        return Number(value || 0).toLocaleString('en-US', {
            maximumFractionDigits: 0,
        });
    }

    function numericCode(value) {
        const digits = String(value || '').replace(/[^\d.]/g, '');
        return digits === '' ? 0 : Number(digits) || 0;
    }

    function reindexLines() {
        linesBody.querySelectorAll('.doc-entry-line-row').forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                const field = input.name.split('[').pop().replace(']', '');
                input.name = `lines[${index}][${field}]`;
            });
        });
    }

    function recalculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        let totalGroupCodes = 0;
        let totalAccountCodes = 0;

        linesBody.querySelectorAll('.doc-entry-line-row').forEach((row) => {
            const debit = parseFloat(row.querySelector('.debit-input')?.value || '0') || 0;
            const credit = parseFloat(row.querySelector('.credit-input')?.value || '0') || 0;
            totalDebit += debit;
            totalCredit += credit;
            totalGroupCodes += numericCode(row.querySelector('.group-code-input')?.value);
            totalAccountCodes += numericCode(row.querySelector('.account-code-input')?.value);
        });

        const variance = totalDebit - totalCredit;
        const totalDebitEl = root.querySelector('#total-debit-display');
        const totalCreditEl = root.querySelector('#total-credit-display');
        const varianceEl = root.querySelector('#variance-display');
        const varianceAlert = root.querySelector('#variance-alert');
        const totalCostCenterEl = root.querySelector('#total-cost-center-codes');
        const totalAccountEl = root.querySelector('#total-account-codes');

        if (totalDebitEl) totalDebitEl.textContent = formatAmount(totalDebit);
        if (totalCreditEl) totalCreditEl.textContent = formatAmount(totalCredit);
        if (totalCostCenterEl) totalCostCenterEl.textContent = formatCodeTotal(totalGroupCodes);
        if (totalAccountEl) totalAccountEl.textContent = formatCodeTotal(totalAccountCodes);
        if (varianceEl) {
            varianceEl.textContent = formatAmount(variance);
            varianceEl.classList.toggle('text-danger', Math.abs(variance) > 0.0001);
            varianceEl.classList.toggle('text-success', Math.abs(variance) <= 0.0001);
        }
        if (varianceAlert) {
            varianceAlert.classList.toggle('d-none', Math.abs(variance) <= 0.0001);
        }
    }

    function bindRowEvents(row) {
        row.querySelector('.remove-line-btn')?.addEventListener('click', () => {
            if (linesBody.querySelectorAll('.doc-entry-line-row').length <= 1) {
                return;
            }
            row.remove();
            reindexLines();
            recalculateTotals();
        });

        row.querySelectorAll('.debit-input, .credit-input, .group-code-input, .account-code-input').forEach((input) => {
            input.addEventListener('input', recalculateTotals);
        });

        const accountInput = row.querySelector('.account-code-input');
        const descriptionInput = row.querySelector('.account-description-input');
        if (accountInput && descriptionInput) {
            accountInput.addEventListener('blur', async () => {
                const code = accountInput.value.trim();
                if (code === '') {
                    return;
                }

                try {
                    const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(code)}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json();
                    const exact = (payload.data || []).find((item) => item.code === code);
                    if (exact && !descriptionInput.value.trim()) {
                        descriptionInput.value = exact.description || '';
                    }
                } catch (error) {
                    console.error(error);
                }
            });
        }
    }

    function createLineRow(index) {
        const row = document.createElement('tr');
        row.className = 'doc-entry-line-row';
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm group-code-input font-monospace" name="lines[${index}][group_code]" value=""></td>
            <td><input type="text" class="form-control form-control-sm account-code-input font-monospace" name="lines[${index}][account_code]" value=""></td>
            <td><input type="text" class="form-control form-control-sm account-description-input" name="lines[${index}][description]" value=""></td>
            <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end debit-input" name="lines[${index}][debit]" value=""></td>
            <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end credit-input" name="lines[${index}][credit]" value=""></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger border-0 remove-line-btn" title="Remove line">
                    <i class="fa-light fa-trash"></i>
                </button>
            </td>
        `;
        return row;
    }

    linesBody.querySelectorAll('.doc-entry-line-row').forEach(bindRowEvents);

    addLineBtn.addEventListener('click', () => {
        const index = linesBody.querySelectorAll('.doc-entry-line-row').length;
        const row = createLineRow(index);
        linesBody.appendChild(row);
        bindRowEvents(row);
        reindexLines();
    });

    recalculateTotals();
})();
</script>
@endif
