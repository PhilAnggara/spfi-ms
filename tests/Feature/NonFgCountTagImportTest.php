<?php

use App\Models\CountTagLocation;
use App\Models\CountTagSection;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NonFgCountTag;
use App\Models\UnitOfMeasure;
use App\Services\CountTag\NonFgCountTagLegacyImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['accounting_inventory.count_tag.connection' => config('database.default')]);

    seedLegacyCountTagImportTables();
});

it('creates count tag schema tables via migration', function () {
    expect(Schema::hasTable('count_tag_locations'))->toBeTrue();
    expect(Schema::hasTable('count_tag_sections'))->toBeTrue();
    expect(Schema::hasTable('non_fg_count_tags'))->toBeTrue();
});

it('imports locations sections and count tags from legacy tables', function () {
    $partsCategory = ItemCategory::query()->create([
        'name' => 'PARTS',
        'code' => 'PARTS-CT',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $item = Item::query()->create([
        'name' => 'Imported Count Tag Item',
        'code' => 'CTIMP001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $partsCategory->id,
        'type' => 'Spare Part',
        'stock_on_hand' => 5,
        'is_active' => true,
    ]);

    DB::table('tblLocation')->insert([
        ['Id' => 1, 'Location' => 'BONITO'],
        ['Id' => 2, 'Location' => 'SKIPJACK'],
    ]);

    DB::table('tblSection')->insert([
        'Id' => 10,
        'LocationId' => 1,
        'Section' => 'A',
        'Row' => 5,
        'Column' => 8,
    ]);

    DB::table('tblCategory')->insert([
        'Id' => 5,
        'CategoryName' => 'PARTS',
    ]);

    DB::table('tblNonFGCountTag')->insert([
        [
            'Id' => 1001,
            'Category' => 5,
            'CountTag' => 'CT-1001',
            'CountTagDate' => '2024-01-15',
            'Location' => 'BONTIO',
            'SectionLocation' => 'A',
            'Row' => 2,
            'Col' => 3,
            'Levl' => 1,
            'ItemCode' => $item->code,
            'Size' => 'M',
            'Condition' => 'Good',
            'UOM' => 'PCS',
            'QTY' => 12.5,
            'Trandate' => '2024-01-31',
            'Grp' => 'STOREKEEPER',
        ],
        [
            'Id' => 1002,
            'Category' => -1,
            'CountTag' => 'CT-1002',
            'CountTagDate' => '2024-01-15',
            'Location' => '',
            'SectionLocation' => '',
            'Row' => null,
            'Col' => null,
            'Levl' => null,
            'ItemCode' => 'UNKNOWN999',
            'Size' => null,
            'Condition' => '',
            'UOM' => 'PCS',
            'QTY' => 3,
            'Trandate' => '2024-01-31',
            'Grp' => 'AUDITOR',
        ],
    ]);

    $result = app(NonFgCountTagLegacyImporter::class)->import(500);

    expect($result['locations']['created'])->toBe(2);
    expect($result['sections']['created'])->toBe(1);
    expect($result['tags']['created'])->toBe(2);

    $matched = NonFgCountTag::query()->where('legacy_id', 1001)->first();
    expect($matched)->not->toBeNull();
    expect($matched->item_id)->toBe($item->id);
    expect($matched->item_category_id)->toBe($partsCategory->id);
    expect($matched->location_name)->toBe('BONITO');
    expect($matched->location_id)->toBe(CountTagLocation::query()->where('name', 'BONITO')->value('id'));
    expect($matched->section_id)->toBe(CountTagSection::query()->where('legacy_id', 10)->value('id'));
    expect($matched->created_by)->toBeNull();
    expect($matched->created_by_name)->toBe('STOREKEEPER');
    expect($matched->group_name)->toBe('STOREKEEPER');
    expect((float) $matched->qty)->toBe(12.5);
    expect($matched->meta['legacy']['Id'])->toBe(1001);
    expect($matched->meta['mapping']['location']['raw_location'])->toBe('BONTIO');

    $unmatched = NonFgCountTag::query()->where('legacy_id', 1002)->first();
    expect($unmatched)->not->toBeNull();
    expect($unmatched->item_id)->toBeNull();
    expect($unmatched->item_code)->toBe('UNKNOWN999');
    expect($unmatched->item_category_id)->toBeNull();
    expect($unmatched->location_id)->toBeNull();
});

it('is idempotent when re-run on the same legacy rows', function () {
    DB::table('tblLocation')->insert(['Id' => 1, 'Location' => 'BONITO']);
    DB::table('tblSection')->insert([
        'Id' => 1,
        'LocationId' => 1,
        'Section' => 'A',
        'Row' => 1,
        'Column' => 1,
    ]);
    DB::table('tblNonFGCountTag')->insert([
        'Id' => 2001,
        'Category' => -1,
        'CountTag' => 'CT-2001',
        'CountTagDate' => '2024-02-01',
        'Location' => 'BONITO',
        'SectionLocation' => 'A',
        'Row' => 1,
        'Col' => 1,
        'Levl' => 1,
        'ItemCode' => 'NOITEM01',
        'Size' => null,
        'Condition' => null,
        'UOM' => 'PCS',
        'QTY' => 1,
        'Trandate' => '2024-02-28',
        'Grp' => 'STOREKEEPER',
    ]);

    $importer = app(NonFgCountTagLegacyImporter::class);
    $first = $importer->import(500);
    $second = $importer->import(500);

    expect($first['tags']['created'])->toBe(1);
    expect($second['tags']['created'])->toBe(0);
    expect($second['tags']['updated'])->toBe(1);
    expect(NonFgCountTag::query()->count())->toBe(1);
});

it('runs via artisan count-tag:import-non-fg command', function () {
    DB::table('tblLocation')->insert(['Id' => 1, 'Location' => 'BONITO']);
    DB::table('tblNonFGCountTag')->insert([
        'Id' => 3001,
        'Category' => -1,
        'CountTag' => 'CT-3001',
        'CountTagDate' => '2024-03-01',
        'Location' => 'BONITO',
        'SectionLocation' => '',
        'Row' => null,
        'Col' => null,
        'Levl' => null,
        'ItemCode' => 'NOITEM02',
        'Size' => null,
        'Condition' => null,
        'UOM' => 'PCS',
        'QTY' => 2,
        'Trandate' => '2024-03-31',
        'Grp' => 'STOREKEEPER',
    ]);

    Artisan::call('count-tag:import-non-fg', ['--chunk' => 100]);

    expect(Artisan::output())->toContain('Import completed');
    expect(NonFgCountTag::query()->where('legacy_id', 3001)->exists())->toBeTrue();
});

function seedLegacyCountTagImportTables(): void
{
    if (! Schema::hasTable('tblLocation')) {
        Schema::create('tblLocation', function ($table): void {
            $table->unsignedBigInteger('Id')->primary();
            $table->string('Location');
        });
    }

    if (! Schema::hasTable('tblSection')) {
        Schema::create('tblSection', function ($table): void {
            $table->unsignedBigInteger('Id')->primary();
            $table->unsignedBigInteger('LocationId');
            $table->string('Section');
            $table->unsignedInteger('Row')->nullable();
            $table->unsignedInteger('Column')->nullable();
        });
    }

    if (! Schema::hasTable('tblCategory')) {
        Schema::create('tblCategory', function ($table): void {
            $table->unsignedBigInteger('Id')->primary();
            $table->string('CategoryName');
        });
    }

    if (! Schema::hasTable('tblNonFGCountTag')) {
        Schema::create('tblNonFGCountTag', function ($table): void {
            $table->unsignedBigInteger('Id')->primary();
            $table->integer('Category')->nullable();
            $table->string('CountTag')->nullable();
            $table->date('CountTagDate')->nullable();
            $table->string('Location')->nullable();
            $table->string('SectionLocation')->nullable();
            $table->unsignedInteger('Row')->nullable();
            $table->unsignedInteger('Col')->nullable();
            $table->unsignedInteger('Levl')->nullable();
            $table->string('ItemCode')->nullable();
            $table->string('Size')->nullable();
            $table->string('Condition')->nullable();
            $table->string('UOM')->nullable();
            $table->decimal('QTY', 20, 5)->default(0);
            $table->date('Trandate')->nullable();
            $table->string('Grp')->nullable();
        });
    }

    DB::table('tblLocation')->delete();
    DB::table('tblSection')->delete();
    DB::table('tblCategory')->delete();
    DB::table('tblNonFGCountTag')->delete();
}
