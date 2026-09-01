<?php

namespace Database\Seeders;

use App\Models\PrintCalibrationProfile;
use App\Services\Print\PrintCalibrationService;
use Illuminate\Database\Seeder;

class PrintCalibrationProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = app(PrintCalibrationService::class);

        foreach (PrintCalibrationProfile::DOCUMENT_TYPES as $documentType) {
            $anchor = $service->getDesignAnchor($documentType);
            $label = $documentType === PrintCalibrationProfile::DOCUMENT_TYPE_RR
                ? 'Default RR'
                : 'Default TS';

            PrintCalibrationProfile::query()->updateOrCreate(
                [
                    'document_type' => $documentType,
                    'name' => $label,
                ],
                [
                    'measured_anchor_x_mm' => $anchor['x'],
                    'measured_anchor_y_mm' => $anchor['y'],
                    'is_default' => true,
                    'description' => 'Default profile aligned to the design anchor (zero offset).',
                ]
            );
        }
    }
}
