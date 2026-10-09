<?php

namespace App\Services\CountTag;

use Illuminate\Support\Collection;

class NonFgCountTagCategoryMapper
{
    /**
     * Legacy tblCategory.CategoryName values mapped to local item_categories.name.
     *
     * @var array<int, string>
     */
    public const LEGACY_ID_TO_CATEGORY_NAME = [
        1 => 'CAN',
        2 => 'CARTON',
        3 => 'LABEL',
        4 => 'FACTORY SUPPLIES',
        5 => 'PARTS',
        6 => 'OFFICE SUPPLIES',
        7 => 'SPICES AND INGREDIENTS',
        8 => 'OTHERS',
        9 => 'FUEL',
        10 => 'PLASTIC BAG FPL',
    ];

    /**
     * @param  Collection<int, string>  $legacyCategories  legacy id => CategoryName
     * @param  Collection<string, int>  $localCategoriesByName  name => id
     */
    public function resolveCategoryId(int $legacyCategoryId, Collection $legacyCategories, Collection $localCategoriesByName): ?int
    {
        if ($legacyCategoryId < 0) {
            return null;
        }

        $categoryName = $legacyCategories->get($legacyCategoryId)
            ?? self::LEGACY_ID_TO_CATEGORY_NAME[$legacyCategoryId]
            ?? null;

        if ($categoryName === null) {
            return null;
        }

        $normalizedName = strtoupper(trim($categoryName));

        return $localCategoriesByName->get($normalizedName)
            ?? $localCategoriesByName->get($categoryName);
    }
}
