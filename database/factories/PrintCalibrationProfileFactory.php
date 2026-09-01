<?php

namespace Database\Factories;

use App\Models\PrintCalibrationProfile;
use App\Services\Print\PrintCalibrationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintCalibrationProfile>
 */
class PrintCalibrationProfileFactory extends Factory
{
    protected $model = PrintCalibrationProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentType = fake()->randomElement(PrintCalibrationProfile::DOCUMENT_TYPES);
        $anchor = app(PrintCalibrationService::class)->getDesignAnchor($documentType);

        return [
            'document_type' => $documentType,
            'name' => fake()->words(3, true),
            'measured_anchor_x_mm' => $anchor['x'],
            'measured_anchor_y_mm' => $anchor['y'],
            'is_default' => false,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function rr(): static
    {
        return $this->state(function (): array {
            $anchor = app(PrintCalibrationService::class)->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_RR);

            return [
                'document_type' => PrintCalibrationProfile::DOCUMENT_TYPE_RR,
                'measured_anchor_x_mm' => $anchor['x'],
                'measured_anchor_y_mm' => $anchor['y'],
            ];
        });
    }

    public function ts(): static
    {
        return $this->state(function (): array {
            $anchor = app(PrintCalibrationService::class)->getDesignAnchor(PrintCalibrationProfile::DOCUMENT_TYPE_TS);

            return [
                'document_type' => PrintCalibrationProfile::DOCUMENT_TYPE_TS,
                'measured_anchor_x_mm' => $anchor['x'],
                'measured_anchor_y_mm' => $anchor['y'],
            ];
        });
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
