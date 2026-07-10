<?php

it('uses isolated sqlite database during tests', function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});

it('does not use legacy database connections during tests', function () {
    expect(config('database.default'))->not->toStartWith('legacy_');
});
