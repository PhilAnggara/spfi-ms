<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Print\PrintCalibrationService;
use Illuminate\Http\Request;

trait ResolvesPrintCalibration
{
    /**
     * @return array{x: float, y: float}
     */
    protected function resolvePrintCalibrationOffsets(Request $request, string $documentType): array
    {
        $service = app(PrintCalibrationService::class);

        $calibrationFields = [
            'calibration_profile_id',
            'measured_anchor_x_mm',
            'measured_anchor_y_mm',
            'nudge_x_mm',
            'nudge_y_mm',
        ];

        if ($request->hasAny($calibrationFields)) {
            $validated = $request->validate($service->validationRules($documentType));
            $input = $service->extractCalibrationInput($validated, $documentType);
        } else {
            $input = $service->extractCalibrationInput([], $documentType);
        }

        return $service->resolve(
            $documentType,
            $input['calibration_profile_id'],
            $input['measured_anchor_x_mm'],
            $input['measured_anchor_y_mm'],
            $input['nudge_x_mm'],
            $input['nudge_y_mm'],
        );
    }
}
