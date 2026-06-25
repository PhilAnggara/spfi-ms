<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer(['pdf.layouts.analytical', 'pdf.reports.*'], function ($view) {
            $view->with([
                'fmtDate' => fn (mixed $value) => PdfFormatters::date($value),
                'fmtMoney' => fn (float|int|string $value) => PdfFormatters::money($value),
                'fmtQty' => fn (float|int|string $value) => PdfFormatters::qty($value),
            ]);
        });
    }
}
