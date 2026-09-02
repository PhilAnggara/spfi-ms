<?php

namespace App\Providers;

use App\Models\Delivery;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\TransferSlip;
use App\Models\TransferSlipItem;
use App\Observers\AccountingInventoryDraftObserver;
use App\Services\Accounting\GlJournalEncoder;
use App\Services\Accounting\NullGlJournalEncoder;
use App\Support\PdfFormatters;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GlJournalEncoder::class, NullGlJournalEncoder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        ReceivingReport::observe(AccountingInventoryDraftObserver::class);
        ReceivingReportItem::observe(AccountingInventoryDraftObserver::class);
        TransferSlip::observe(AccountingInventoryDraftObserver::class);
        TransferSlipItem::observe(AccountingInventoryDraftObserver::class);
        Delivery::observe(AccountingInventoryDraftObserver::class);

        View::composer(['pdf.layouts.analytical', 'pdf.reports.*'], function ($view) {
            $view->with([
                'fmtDate' => fn (mixed $value) => PdfFormatters::date($value),
                'fmtMoney' => fn (float|int|string $value) => PdfFormatters::money($value),
                'fmtQty' => fn (float|int|string $value) => PdfFormatters::qty($value),
            ]);
        });
    }
}
