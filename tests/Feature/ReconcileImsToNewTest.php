<?php

use App\Services\Reconcile\DocumentFingerprint;
use App\Services\Reconcile\ReconcileDeltaAuditor;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\mock;

it('builds stable line signatures regardless of order', function () {
    $a = DocumentFingerprint::linesSignature([
        ['code' => 'B', 'qty' => 2],
        ['code' => 'A', 'qty' => 1.5],
    ]);
    $b = DocumentFingerprint::linesSignature([
        ['code' => 'a', 'qty' => 1.5],
        ['code' => 'b', 'qty' => 2],
    ]);

    expect($a)->toBe($b);
    expect(DocumentFingerprint::hash($a))->toBe(DocumentFingerprint::hash($b));
});

it('normalizes dates to Y-m-d', function () {
    expect(DocumentFingerprint::normalizeDate('2026-07-15 10:00:00'))->toBe('2026-07-15');
    expect(DocumentFingerprint::normalizeDate(null))->toBe('');
});

it('creates reconciliation audit tables', function () {
    expect(Schema::hasTable('reconciliation_import_logs'))->toBeTrue();
    expect(Schema::hasTable('reconciliation_number_maps'))->toBeTrue();
});

it('lists reconcile command help and rejects invalid conflict', function () {
    $this->artisan('reconcile:ims-to-new', ['--conflict' => 'overwrite'])
        ->expectsOutputToContain('Invalid --conflict')
        ->assertFailed();
});

it('runs report mode via mocked auditor', function () {
    mock(ReconcileDeltaAuditor::class, function ($mock): void {
        $mock->shouldReceive('audit')
            ->once()
            ->withArgs(function (string $since, $only, bool $writeCsv): bool {
                return $since === '2026-07-15' && $only === ['prs'] && $writeCsv === true;
            })
            ->andReturn([
                'since' => '2026-07-15',
                'generated_at' => now()->toDateTimeString(),
                'datasets' => [
                    'prs' => [
                        'ims_only' => [['number' => '70460021499']],
                        'new_only' => [],
                        'content_mismatches' => [],
                        'match_count' => 0,
                    ],
                ],
                'stock_mismatches' => [],
                'report_dir' => storage_path('app/reconcile-reports/test'),
            ]);
    });

    $this->artisan('reconcile:ims-to-new', [
        '--report' => true,
        '--since' => '2026-07-15',
        '--only' => 'prs',
    ])
        ->expectsOutputToContain('IMS → spfi_ms reconcile')
        ->expectsOutputToContain('prs')
        ->assertSuccessful();
});

it('blocks transactional writes when reconcile freeze is enabled', function () {
    config(['reconcile.freeze_writes' => true]);

    $middleware = new \App\Http\Middleware\BlockTransactionalWritesDuringReconcile;
    $request = \Illuminate\Http\Request::create('/prs', 'POST');
    $request->headers->set('Accept', 'application/json');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['POST'], '/prs', fn () => null);
        $route->name('prs.store');

        return $route;
    });

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(503);
    expect($response->getContent())->toContain('freeze_writes');
});

it('allows safe methods when reconcile freeze is enabled', function () {
    config(['reconcile.freeze_writes' => true]);

    $middleware = new \App\Http\Middleware\BlockTransactionalWritesDuringReconcile;
    $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/dashboard', fn () => null);
        $route->name('dashboard');

        return $route;
    });

    $response = $middleware->handle($request, fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});

it('allows allowlisted export routes during freeze', function () {
    config(['reconcile.freeze_writes' => true]);

    $middleware = new \App\Http\Middleware\BlockTransactionalWritesDuringReconcile;
    $request = \Illuminate\Http\Request::create('/prs/export', 'POST');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['POST'], '/prs/export', fn () => null);
        $route->name('prs.export');

        return $route;
    });

    $response = $middleware->handle($request, fn () => response('exported', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('resolves optional legacy users without type error when value is empty', function () {
    $importer = app(\App\Services\Legacy\LegacyIncrementalImporter::class);
    $ref = new ReflectionClass($importer);

    $prepare = $ref->getMethod('prepareLegacyUserLookup');
    $prepare->setAccessible(true);
    $prepare->invoke($importer);

    $method = $ref->getMethod('resolveOptionalUserId');
    $method->setAccessible(true);

    expect($method->invoke($importer, null))->toBeNull();
    expect($method->invoke($importer, ''))->toBeNull();
    expect($method->invoke($importer, 'NULL'))->toBeNull();
});

it('renders freeze banner when enabled', function () {
    $html = view('partials.reconcile-freeze-banner')->render();

    expect($html)->toContain('IMS');
    expect($html)->toContain('dibekukan');
    expect($html)->toContain('Rekonsiliasi');
});
