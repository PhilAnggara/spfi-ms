<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;

abstract class TestCase extends BaseTestCase
{
    use CanConfigureMigrationCommands;

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return array_merge(parent::migrateFreshUsing(), [
            '--force' => true,
        ]);
    }
}
