<?php

namespace App\Services\Print;

use App\Models\PrintCalibrationProfile;

class PrintCalibrationService
{
    /** @var array<string, array{x: float, y: float}> */
    private const MEASURED_RANGES = [
        PrintCalibrationProfile::DOCUMENT_TYPE_RR => ['x_max' => 100.0, 'y_max' => 120.0],
        PrintCalibrationProfile::DOCUMENT_TYPE_TS => ['x_max' => 100.0, 'y_max' => 90.0],
    ];

    /**
     * @return array{x: float, y: float, label: string}
     */
    public function getDesignAnchor(string $documentType): array
    {
        $configKey = $this->configKeyFor($documentType);

        return [
            'x' => (float) config("{$configKey}.calibration_anchor.x_mm"),
            'y' => (float) config("{$configKey}.calibration_anchor.y_mm"),
            'label' => (string) config("{$configKey}.calibration_anchor.label", 'Top-left corner of the background table'),
        ];
    }

    /**
     * @return array{x: float, y: float}
     */
    public function offsetFromMeasured(string $documentType, float $measuredX, float $measuredY): array
    {
        $design = $this->getDesignAnchor($documentType);

        return [
            'x' => round($measuredX - $design['x'], 2),
            'y' => round($measuredY - $design['y'], 2),
        ];
    }

    /**
     * @return array{x: float, y: float}
     */
    public function resolve(
        string $documentType,
        ?int $profileId = null,
        ?float $measuredX = null,
        ?float $measuredY = null,
        ?float $nudgeX = null,
        ?float $nudgeY = null,
    ): array {
        $configKey = $this->configKeyFor($documentType);
        $baseX = (float) config("{$configKey}.offset_x_mm", 0);
        $baseY = (float) config("{$configKey}.offset_y_mm", 0);

        $resolvedMeasuredX = $measuredX;
        $resolvedMeasuredY = $measuredY;

        if ($resolvedMeasuredX === null || $resolvedMeasuredY === null) {
            $profile = $profileId !== null
                ? PrintCalibrationProfile::query()->find($profileId)
                : PrintCalibrationProfile::defaultFor($documentType);

            if ($profile !== null && $profile->document_type === $documentType) {
                $resolvedMeasuredX = (float) $profile->measured_anchor_x_mm;
                $resolvedMeasuredY = (float) $profile->measured_anchor_y_mm;
            }
        }

        $offsetX = $baseX;
        $offsetY = $baseY;

        if ($resolvedMeasuredX !== null && $resolvedMeasuredY !== null) {
            $computed = $this->offsetFromMeasured($documentType, $resolvedMeasuredX, $resolvedMeasuredY);
            $offsetX += $computed['x'];
            $offsetY += $computed['y'];
        }

        $offsetX += (float) ($nudgeX ?? 0);
        $offsetY += (float) ($nudgeY ?? 0);

        return [
            'x' => round($offsetX, 2),
            'y' => round($offsetY, 2),
        ];
    }

    /**
     * @return array{measured_anchor_x_mm: float, measured_anchor_y_mm: float, nudge_x_mm: float, nudge_y_mm: float, calibration_profile_id: int|null}
     */
    public function extractCalibrationInput(array $input, string $documentType): array
    {
        return [
            'calibration_profile_id' => isset($input['calibration_profile_id']) && $input['calibration_profile_id'] !== ''
                ? (int) $input['calibration_profile_id']
                : null,
            'measured_anchor_x_mm' => $this->nullableFloat($input['measured_anchor_x_mm'] ?? null),
            'measured_anchor_y_mm' => $this->nullableFloat($input['measured_anchor_y_mm'] ?? null),
            'nudge_x_mm' => $this->nullableFloat($input['nudge_x_mm'] ?? null) ?? 0.0,
            'nudge_y_mm' => $this->nullableFloat($input['nudge_y_mm'] ?? null) ?? 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(string $documentType): array
    {
        $ranges = self::MEASURED_RANGES[$documentType] ?? ['x_max' => 100.0, 'y_max' => 120.0];

        return [
            'calibration_profile_id' => ['nullable', 'integer', 'exists:print_calibration_profiles,id'],
            'measured_anchor_x_mm' => ['nullable', 'numeric', 'min:0', 'max:'.$ranges['x_max']],
            'measured_anchor_y_mm' => ['nullable', 'numeric', 'min:0', 'max:'.$ranges['y_max']],
            'nudge_x_mm' => ['nullable', 'numeric', 'min:-5', 'max:5'],
            'nudge_y_mm' => ['nullable', 'numeric', 'min:-5', 'max:5'],
        ];
    }

    private function configKeyFor(string $documentType): string
    {
        return match ($documentType) {
            PrintCalibrationProfile::DOCUMENT_TYPE_RR => 'receiving-report',
            PrintCalibrationProfile::DOCUMENT_TYPE_TS => 'transfer-slip',
            default => throw new \InvalidArgumentException("Unknown document type: {$documentType}"),
        };
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
