@php
    $documentType = strtoupper((string) ($documentType ?? 'RR'));
    $profiles = $profiles ?? collect();
    $designAnchor = $designAnchor ?? ['x' => 0, 'y' => 0, 'label' => 'Top-left corner of the background table'];
    $paperWidthMm = (int) ($paperWidthMm ?? config($documentType === 'RR' ? 'receiving-report.paper.width_mm' : 'transfer-slip.paper.width_mm', 215));
    $paperHeightMm = (int) ($paperHeightMm ?? config($documentType === 'RR' ? 'receiving-report.paper.height_mm' : 'transfer-slip.paper.height_mm', 160));
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="small text-muted mb-1">Design reference (perfect form)</div>
        <div>
            <strong>{{ $designAnchor['label'] }}</strong>:
            {{ number_format($designAnchor['x'], 2) }} mm to the right,
            {{ number_format($designAnchor['y'], 2) }} mm downward from the top-left corner of the paper.
        </div>
    </div>
</div>

@include('partials.print-setup-guide', [
    'paperWidthMm' => $paperWidthMm,
    'paperHeightMm' => $paperHeightMm,
    'documentType' => $documentType,
    'collapseSuffix' => 'admin-index-'.strtolower($documentType),
])

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <table class="table table-striped text-center mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Measured X (mm)</th>
                    <th>Measured Y (mm)</th>
                    <th>Default</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profiles as $profile)
                    <tr>
                        <td class="text-start">{{ $profile->name }}</td>
                        <td>{{ number_format($profile->measured_anchor_x_mm, 2) }}</td>
                        <td>{{ number_format($profile->measured_anchor_y_mm, 2) }}</td>
                        <td>
                            @if ($profile->is_default)
                                <span class="badge bg-light-success text-success">Default</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @can('manage-print-calibration')
                            <div class="btn-group btn-group-sm">
                                <a
                                    href="{{ route('print-calibration-profiles.calibrate.edit', $profile) }}"
                                    class="btn icon"
                                    title="Calibrate"
                                >
                                    <i class="fa-light fa-ruler-combined text-primary"></i>
                                </a>
                                <form
                                    action="{{ route('print-calibration-profiles.destroy', $profile) }}"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Delete profile {{ $profile->name }}?')"
                                >
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn icon" title="Delete">
                                        <i class="fa-light fa-trash text-secondary"></i>
                                    </button>
                                </form>
                            </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">No profiles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
