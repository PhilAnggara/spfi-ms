<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->cleanupExistingCodeEmployees();

        if ($this->hasIndex('employees', 'employees_code_employee_index')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('employees_code_employee_index');
            });
        }

        if ($this->hasIndex('employees', 'employees_code_employee_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_code_employee_unique');
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('code_employee', 100)->nullable(false)->change();
        });

        if (! $this->hasIndex('employees', 'employees_code_employee_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('code_employee');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('employees', 'employees_code_employee_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_code_employee_unique');
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('code_employee', 100)->nullable()->change();
        });

        if (! $this->hasIndex('employees', 'employees_code_employee_index')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('code_employee');
            });
        }
    }

    private function cleanupExistingCodeEmployees(): void
    {
        $rows = DB::table('employees')
            ->select(['id', 'code_employee'])
            ->orderBy('id')
            ->get();

        $owners = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $base = $this->normalizeCode($row->code_employee) ?? "EMP-{$id}";
            $candidate = $base;
            $counter = 2;

            while (true) {
                $normalized = strtolower($candidate);

                if (! isset($owners[$normalized]) || $owners[$normalized] === $id) {
                    $owners[$normalized] = $id;
                    break;
                }

                $suffix = '-dup-' . $counter;
                $baseLimit = max(1, 100 - strlen($suffix));
                $candidate = rtrim(substr($base, 0, $baseLimit)) . $suffix;
                $counter++;
            }

            if ($candidate !== $row->code_employee) {
                DB::table('employees')
                    ->where('id', $id)
                    ->update([
                        'code_employee' => $candidate,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function normalizeCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
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
