<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->hasIndex('employees', 'employees_code_employee_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_code_employee_unique');
            });
        }

        if ($this->hasIndex('employees', 'employees_id_biometrik_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_id_biometrik_unique');
            });
        }

        if (! $this->hasIndex('employees', 'employees_code_employee_index')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('code_employee');
            });
        }

        if (! $this->hasIndex('employees', 'employees_id_biometrik_index')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('id_biometrik');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex('employees', 'employees_code_employee_index')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('employees_code_employee_index');
            });
        }

        if ($this->hasIndex('employees', 'employees_id_biometrik_index')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('employees_id_biometrik_index');
            });
        }

        if (! $this->hasIndex('employees', 'employees_code_employee_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('code_employee');
            });
        }

        if (! $this->hasIndex('employees', 'employees_id_biometrik_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('id_biometrik');
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            $result = DB::selectOne(
                'SELECT TOP 1 1 AS [found]
                 FROM sys.indexes i
                 INNER JOIN sys.tables t ON i.object_id = t.object_id
                 INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
                 WHERE t.name = ?
                   AND i.name = ?
                   AND s.name = SCHEMA_NAME()',
                [$tableName, $indexName]
            );

            return $result !== null;
        }

        return Schema::hasIndex($tableName, $indexName);
    }
};
