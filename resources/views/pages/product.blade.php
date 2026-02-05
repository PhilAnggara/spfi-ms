@extends('layouts.app')
@section('title', ' | Product')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Product</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Product
                    </button>
                </div>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            {{-- <div class="card-header">
                <h5 class="card-title">
                    PRS Data
                </h5>
            </div> --}}
            <div class="card-body">
                <table class="table table-striped text-center text-nowrap" id="product-table" data-source="{{ route('product.datatables') }}">
                    <thead>
                        <tr>
                            <th class="text-center d-none">ID</th>
                            <th class="text-center">Product Code</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Unit</th>
                            <th class="text-center">Category</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </section>
</div>
@include('includes.modals.product-modal')
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush

@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = $('#product-table');
        const tableUrl = table.data('source');
        const csrfToken = '{{ csrf_token() }}';
        const updateRouteTemplate = '{{ route('product.update', '__ID__') }}';
        const editModalElement = document.getElementById('edit-modal');
        const editModal = editModalElement ? new bootstrap.Modal(editModalElement) : null;

        // Escape sederhana untuk mencegah HTML/JS injection pada data tabel
        const escapeHtml = (value) => {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const dataTable = table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: tableUrl,
                type: 'GET'
            },
            // Urutkan berdasarkan id terbaru (kolom tersembunyi)
            order: [[0, 'desc']],
            columns: [
                {
                    data: 'id',
                    visible: false,
                    searchable: false
                },
                {
                    data: 'code',
                    render: function(data) {
                        const safeCode = escapeHtml(data ?? '-');
                        return `
                            <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill copy-code" data-code="${safeCode}">
                                <i class="fa-solid fa-regular fa-clipboard"></i>
                                ${safeCode}
                            </button>
                        `;
                    }
                },
                {
                    data: 'name',
                    render: function(data) {
                        const rawName = data ?? '-';
                        const safeName = escapeHtml(rawName);
                        const displayName = rawName.length > 30 ? `${escapeHtml(rawName.slice(0, 30))}...` : safeName;
                        return `<span class="copy-name" data-name="${safeName}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="${safeName}" style="cursor: pointer">${displayName}</span>`;
                    }
                },
                {
                    data: 'unit_name',
                    render: function(data) {
                        return `<span class="badge bg-light-secondary">${escapeHtml(data ?? '-')}</span>`;
                    }
                },
                {
                    data: 'category_name',
                    render: function(data) {
                        return escapeHtml(data ?? '-');
                    }
                },
                {
                    data: 'type',
                    render: function(data) {
                        return escapeHtml(data ?? '-');
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(row) {
                        const safeName = escapeHtml(row.name ?? '-');
                        const editAttrs = `
                            data-id="${row.id}"
                            data-code="${escapeHtml(row.code ?? '')}"
                            data-name="${safeName}"
                            data-unit-id="${row.unit_of_measure_id ?? ''}"
                            data-category-id="${row.category_id ?? ''}"
                            data-type="${escapeHtml(row.type ?? '')}"
                        `;

                        return `
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn icon edit-product" ${editAttrs} data-bs-toggle="modal" data-bs-target="#edit-modal" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                    <i class="fa-light fa-edit text-primary"></i>
                                </button>
                                <button type="button" class="btn icon delete-product" data-id="${row.id}" data-name="${safeName}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
                                    <i class="fa-light fa-trash text-secondary"></i>
                                </button>
                                <form action="{{ route('product.destroy', '__ID__') }}" id="hapus-${row.id}" method="POST">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="delete">
                                </form>
                            </div>
                        `.replace('__ID__', row.id);
                    }
                }
            ]
        });

        $('#product-table tbody').on('click', '.edit-product', function() {
            const button = $(this);
            const itemId = button.data('id');
            const updateUrl = updateRouteTemplate.replace('__ID__', itemId);
            // Helper set value untuk select Choices.js agar UI ikut ter-update
            const setSelectValue = (elementId, value) => {
                const selectElement = document.getElementById(elementId);
                if (!selectElement) {
                    return;
                }

                const normalizedValue = value === null || value === undefined ? '' : String(value);
                if (selectElement.choicesInstance) {
                    selectElement.choicesInstance.removeActiveItems();
                    if (normalizedValue) {
                        selectElement.choicesInstance.setChoiceByValue(normalizedValue);
                    }
                } else {
                    selectElement.value = normalizedValue;
                }

                selectElement.dispatchEvent(new Event('change'));
            };

            document.getElementById('edit-code').value = button.data('code') || '';
            document.getElementById('edit-name').value = button.data('name') || '';
            setSelectValue('edit-unit', button.data('unit-id'));
            setSelectValue('edit-category', button.data('category-id'));
            setSelectValue('edit-type', button.data('type'));
            document.getElementById('edit-form').setAttribute('action', updateUrl);

            const editLabel = document.getElementById('editProductLabel');
            if (editLabel) {
                editLabel.textContent = `Edit Product - ${button.data('name') || ''}`;
            }

            if (editModal) {
                editModal.show();
            }
        });

        $('#product-table tbody').on('click', '.copy-code', function() {
            const code = $(this).data('code');
            // Gunakan helper existing untuk copy ke clipboard
            copyToClipboard(code);
        });

        $('#product-table tbody').on('click', '.copy-name', function() {
            const name = $(this).data('name');
            copyToClipboard(name);
        });

        $('#product-table tbody').on('click', '.delete-product', function() {
            const itemId = $(this).data('id');
            const name = $(this).data('name');
            // Pakai konfirmasi hapus dari helper existing
            hapusData(itemId, 'Delete Product', `Are you sure want to delete ${name}?`);
        });

        @if ($errors->any())
            @if (session('editing_product_id'))
                if (editModal) {
                    editModal.show();
                }
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
