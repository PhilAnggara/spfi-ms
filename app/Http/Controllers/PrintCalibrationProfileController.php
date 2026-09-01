<?php

namespace App\Http\Controllers;

use App\Models\PrintCalibrationProfile;
use App\Services\Print\PrintCalibrationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintCalibrationProfileController extends Controller
{
    private const MM_TO_PT = 2.834645669;

    public function __construct(
        private readonly PrintCalibrationService $calibrationService,
    ) {}

    public function index(Request $request): View
    {
        $activeType = strtoupper((string) $request->query('type', PrintCalibrationProfile::DOCUMENT_TYPE_RR));
        if (! in_array($activeType, PrintCalibrationProfile::DOCUMENT_TYPES, true)) {
            $activeType = PrintCalibrationProfile::DOCUMENT_TYPE_RR;
        }

        $tabs = [];
        foreach (PrintCalibrationProfile::DOCUMENT_TYPES as $documentType) {
            $configKey = $documentType === PrintCalibrationProfile::DOCUMENT_TYPE_RR
                ? 'receiving-report'
                : 'transfer-slip';

            $tabs[$documentType] = [
                'profiles' => PrintCalibrationProfile::query()
                    ->where('document_type', $documentType)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get(),
                'designAnchor' => $this->calibrationService->getDesignAnchor($documentType),
                'paperWidthMm' => (int) config("{$configKey}.paper.width_mm"),
                'paperHeightMm' => (int) config("{$configKey}.paper.height_mm"),
            ];
        }

        return view('pages.print-calibration-profiles.index', [
            'tabs' => $tabs,
            'activeType' => $activeType,
        ]);
    }

    public function calibrate(Request $request, ?PrintCalibrationProfile $printCalibrationProfile = null): View
    {
        $documentType = $printCalibrationProfile?->document_type
            ?? strtoupper((string) $request->query('type', PrintCalibrationProfile::DOCUMENT_TYPE_RR));

        if (! in_array($documentType, PrintCalibrationProfile::DOCUMENT_TYPES, true)) {
            abort(404);
        }

        return view('pages.print-calibration-profiles.calibrate', [
            'profile' => $printCalibrationProfile,
            'documentType' => $documentType,
            'designAnchor' => $this->calibrationService->getDesignAnchor($documentType),
            'paperConfig' => $this->paperConfigFor($documentType),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProfile($request);

        $profile = PrintCalibrationProfile::query()->create($validated);

        if ($profile->is_default) {
            $this->clearOtherDefaults($profile);
        }

        return redirect()
            ->route('print-calibration-profiles.index', ['type' => $profile->document_type])
            ->with('success', 'Print calibration profile has been created.');
    }

    public function update(Request $request, PrintCalibrationProfile $printCalibrationProfile): RedirectResponse
    {
        $validated = $this->validateProfile($request, $printCalibrationProfile);

        $printCalibrationProfile->update($validated);

        if ($printCalibrationProfile->is_default) {
            $this->clearOtherDefaults($printCalibrationProfile);
        }

        return redirect()
            ->route('print-calibration-profiles.index', ['type' => $printCalibrationProfile->document_type])
            ->with('success', 'Print calibration profile has been updated.');
    }

    public function destroy(PrintCalibrationProfile $printCalibrationProfile): RedirectResponse
    {
        $documentType = $printCalibrationProfile->document_type;
        $wasDefault = $printCalibrationProfile->is_default;

        $printCalibrationProfile->delete();

        if ($wasDefault) {
            $replacement = PrintCalibrationProfile::query()
                ->where('document_type', $documentType)
                ->orderBy('name')
                ->first();

            if ($replacement !== null) {
                $replacement->update(['is_default' => true]);
            }
        }

        return redirect()
            ->route('print-calibration-profiles.index', ['type' => $documentType])
            ->with('success', 'Print calibration profile has been deleted.');
    }

    public function previewSample(Request $request)
    {
        $viewData = $this->buildPreviewSampleViewData($request);

        if ($request->query('format') === 'pdf') {
            return $this->streamPreviewSamplePdf($viewData);
        }

        return response()
            ->view('pdf.print-calibration-sample', $viewData)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreviewSampleViewData(Request $request): array
    {
        $documentType = strtoupper((string) $request->query('document_type', PrintCalibrationProfile::DOCUMENT_TYPE_RR));
        if (! in_array($documentType, PrintCalibrationProfile::DOCUMENT_TYPES, true)) {
            abort(404);
        }

        $validated = $request->validate($this->calibrationService->validationRules($documentType));
        $input = $this->calibrationService->extractCalibrationInput($validated, $documentType);
        $offsets = $this->calibrationService->resolve(
            $documentType,
            $input['calibration_profile_id'],
            $input['measured_anchor_x_mm'],
            $input['measured_anchor_y_mm'],
            $input['nudge_x_mm'],
            $input['nudge_y_mm'],
        );

        $paper = $this->paperConfigFor($documentType);

        return [
            'documentType' => $documentType,
            'pageWidthMm' => $paper['width_mm'],
            'pageHeightMm' => $paper['height_mm'],
            'offsetXMm' => $offsets['x'],
            'offsetYMm' => $offsets['y'],
            'designAnchor' => $this->calibrationService->getDesignAnchor($documentType),
            'backgroundImageDataUri' => $this->resolveBackgroundDataUri($documentType),
        ];
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function streamPreviewSamplePdf(array $viewData)
    {
        $documentType = (string) $viewData['documentType'];
        $pageWidthMm = (float) $viewData['pageWidthMm'];
        $pageHeightMm = (float) $viewData['pageHeightMm'];

        $pdf = Pdf::loadView('pdf.print-calibration-sample', $viewData)
            ->setPaper([
                0,
                0,
                $pageWidthMm * self::MM_TO_PT,
                $pageHeightMm * self::MM_TO_PT,
            ]);

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        if ($canvas instanceof \Dompdf\Adapter\CPDF) {
            $canvas->get_cpdf()->setPreferences('PrintScaling', 'None');
        }

        return $pdf->stream("calibration-{$documentType}-".now()->format('YmdHis').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProfile(Request $request, ?PrintCalibrationProfile $existing = null): array
    {
        $documentType = $existing?->document_type
            ?? strtoupper((string) $request->input('document_type'));

        $rules = array_merge([
            'name' => ['required', 'string', 'max:100'],
            'document_type' => ['required', 'string', 'in:'.implode(',', PrintCalibrationProfile::DOCUMENT_TYPES)],
            'is_default' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ], $this->calibrationService->validationRules($documentType));

        $rules['measured_anchor_x_mm'] = ['required', ...array_slice($rules['measured_anchor_x_mm'], 1)];
        $rules['measured_anchor_y_mm'] = ['required', ...array_slice($rules['measured_anchor_y_mm'], 1)];

        $validated = $request->validate($rules);

        return [
            'document_type' => $documentType,
            'name' => $validated['name'],
            'measured_anchor_x_mm' => (float) $validated['measured_anchor_x_mm'],
            'measured_anchor_y_mm' => (float) $validated['measured_anchor_y_mm'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'description' => $validated['description'] ?? null,
        ];
    }

    private function clearOtherDefaults(PrintCalibrationProfile $profile): void
    {
        PrintCalibrationProfile::query()
            ->where('document_type', $profile->document_type)
            ->where('id', '!=', $profile->id)
            ->update(['is_default' => false]);
    }

    /**
     * @return array{width_mm: float, height_mm: float, label: string}
     */
    private function paperConfigFor(string $documentType): array
    {
        $configKey = $documentType === PrintCalibrationProfile::DOCUMENT_TYPE_RR
            ? 'receiving-report'
            : 'transfer-slip';

        return [
            'width_mm' => (float) config("{$configKey}.paper.width_mm"),
            'height_mm' => (float) config("{$configKey}.paper.height_mm"),
            'label' => (string) config("{$configKey}.paper.label"),
        ];
    }

    private function resolveBackgroundDataUri(string $documentType): ?string
    {
        $filename = $documentType === PrintCalibrationProfile::DOCUMENT_TYPE_RR
            ? 'Blank RR.jpg'
            : 'Blank TS.jpg';

        $path = public_path('assets/images/'.$filename);
        if (! is_readable($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path));
    }
}
