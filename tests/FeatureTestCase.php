<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;

abstract class FeatureTestCase extends TestCase
{
    use CanConfigureMigrationCommands;
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $configCache = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($configCache)) {
            unlink($configCache);
        }

        return parent::createApplication();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->withoutMockingConsoleOutput();
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        $seeder = $this->seeder();

        return array_merge(
            [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
                '--force' => true,
            ],
            $seeder ? ['--seeder' => $seeder] : ['--seed' => $this->shouldSeed()]
        );
    }
}
