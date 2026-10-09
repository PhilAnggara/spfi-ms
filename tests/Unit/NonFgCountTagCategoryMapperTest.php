<?php

use App\Services\CountTag\NonFgCountTagCategoryMapper;

it('maps legacy category id to local item category by name', function () {
    $mapper = new NonFgCountTagCategoryMapper;

    $legacyCategories = collect([
        5 => 'PARTS',
    ]);

    $localCategories = collect([
        'PARTS' => 42,
    ]);

    expect($mapper->resolveCategoryId(5, $legacyCategories, $localCategories))->toBe(42);
});

it('returns null for legacy category minus one', function () {
    $mapper = new NonFgCountTagCategoryMapper;

    expect($mapper->resolveCategoryId(-1, collect(), collect(['PARTS' => 1])))->toBeNull();
});

it('falls back to built-in legacy id map when tblCategory row is missing', function () {
    $mapper = new NonFgCountTagCategoryMapper;

    $localCategories = collect([
        'CAN' => 7,
    ]);

    expect($mapper->resolveCategoryId(1, collect(), $localCategories))->toBe(7);
});

it('returns null when local category name is not found', function () {
    $mapper = new NonFgCountTagCategoryMapper;

    expect($mapper->resolveCategoryId(5, collect([5 => 'PARTS']), collect()))->toBeNull();
});
