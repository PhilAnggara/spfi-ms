<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockTransactionalWritesDuringReconcile
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('reconcile.freeze_writes')) {
            return $next($request);
        }

        if ($request->isMethodSafe() || $request->isMethod('OPTIONS') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === '' || ! $this->isFrozenRoute($routeName)) {
            return $next($request);
        }

        if ($this->isAllowlisted($routeName)) {
            return $next($request);
        }

        $message = 'Transactional writes are frozen while IMS → SPFI-MS reconciliation is in progress. '
            .'Prefer IMS for time-critical documents, or set RECONCILE_FREEZE_WRITES=false after sync completes.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'freeze_writes' => true,
            ], 503);
        }

        return redirect()
            ->back(fallback: route('dashboard'))
            ->with('error', $message);
    }

    private function isFrozenRoute(string $routeName): bool
    {
        foreach (config('reconcile.frozen_route_prefixes', []) as $prefix) {
            if (str_starts_with($routeName, (string) $prefix)) {
                return true;
            }
        }

        // Resource routes for PRS use names like prs.store / prs.update
        if (str_starts_with($routeName, 'prs.') || $routeName === 'prs.store' || $routeName === 'prs.update' || $routeName === 'prs.destroy') {
            return true;
        }

        return false;
    }

    private function isAllowlisted(string $routeName): bool
    {
        $suffix = (string) str($routeName)->afterLast('.');

        return in_array($suffix, config('reconcile.frozen_route_allow_suffixes', []), true);
    }
}
