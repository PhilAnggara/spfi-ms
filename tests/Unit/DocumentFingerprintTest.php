<?php

use App\Services\Reconcile\DocumentFingerprint;

it('composes deterministic hashes from associative parts', function () {
    $a = DocumentFingerprint::compose(['po' => '123', 'qty' => '10']);
    $b = DocumentFingerprint::compose(['qty' => '10', 'po' => '123']);

    expect($a)->toBe($b);
});

it('normalizes keys case-insensitively', function () {
    expect(DocumentFingerprint::normalizeKey('  AbC '))->toBe('ABC');
});
