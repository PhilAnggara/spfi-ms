<div class="modal fade text-left modal-borderless" id="export-by-department-modal" tabindex="-1"
    role="dialog" aria-labelledby="exportByDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportByDepartmentModalLabel">PRS Report per Department</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('prs.export-by-department') }}" method="post" target="_blank" class="form">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="dept-start-month">Start Month</label>
                            <input type="month" class="form-control" id="dept-start-month" name="start_month" value="{{ now()->format('Y-m') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="dept-end-month">End Month</label>
                            <input type="month" class="form-control" id="dept-end-month" name="end_month" value="{{ now()->format('Y-m') }}" required>
                        </div>
                        @can('view-all-prs')
                            <div class="col-12">
                                <label class="form-label" for="dept-export-department">Department</label>
                                <select class="form-select" id="dept-export-department" name="department_id">
                                    <option value="">All departments</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->code }} - {{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endcan
                        <div class="col-12">
                            <label class="form-label d-block mb-2">Export Format</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="dept-format-pdf" value="pdf" checked required>
                                    <label class="form-check-label" for="dept-format-pdf">PDF</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="dept-format-excel" value="excel" required>
                                    <label class="form-check-label" for="dept-format-excel">Excel</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">The month range is inclusive based on the PRS Date field. Reports are grouped by department.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-export"></i>
                        Export Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
