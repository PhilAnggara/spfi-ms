<?php

namespace App\Observers;

use App\Models\Delivery;
use App\Models\ItemCategory;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\TransferSlip;
use App\Models\TransferSlipItem;
use App\Services\Accounting\AccountingInventoryPrefiller;
use App\Services\Accounting\AccountingInventoryQueueService;
use Illuminate\Database\Eloquent\Model;

class AccountingInventoryDraftObserver
{
    public function __construct(
        private readonly AccountingInventoryQueueService $queueService,
        private readonly AccountingInventoryPrefiller $prefiller,
    ) {}

    public function saved(ReceivingReport|TransferSlip|Delivery|ReceivingReportItem|TransferSlipItem $model): void
    {
        if ($model instanceof ReceivingReportItem) {
            $model->loadMissing('receivingReport');
            if ($model->receivingReport !== null) {
                $this->createDraftsForSource($model->receivingReport);
            }

            return;
        }

        if ($model instanceof TransferSlipItem) {
            $model->loadMissing('transferSlip');
            if ($model->transferSlip !== null) {
                $this->createDraftsForSource($model->transferSlip);
            }

            return;
        }

        $this->createDraftsForSource($model);
    }

    private function createDraftsForSource(Model $source): void
    {
        $categories = ItemCategory::query()->orderBy('name')->get(['id', 'name']);
        $categoryIds = $this->prefiller->resolveCategoryIdsForSource($source, $categories);

        foreach ($categoryIds as $categoryId) {
            try {
                $this->queueService->findOrCreateDraftForSource($source, $categoryId);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
    }
}
