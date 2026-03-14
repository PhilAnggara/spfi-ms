<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_department_id')->nullable()->constrained('employee_departments')->nullOnDelete();
            $table->string('employee_group')->nullable()->index();
            $table->string('employee_id')->unique();
            $table->string('code_employee')->nullable()->unique();
            $table->string('id_biometrik')->nullable()->unique();
            $table->string('account_no')->nullable();
            $table->string('employee_name')->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable()->index();
            $table->string('legacy_department_code')->nullable()->index();
            $table->string('job_code')->nullable();
            $table->string('position')->nullable();
            $table->string('position_name')->nullable()->index();
            $table->string('pay_type', 50)->nullable();
            $table->date('date_hired')->nullable()->index();
            $table->string('civil_status', 50)->nullable();
            $table->string('cell_phone', 50)->nullable();
            $table->string('identity_card_no')->nullable();
            $table->string('insurance_no')->nullable();
            $table->string('mothers_name')->nullable();
            $table->string('passport')->nullable();
            $table->decimal('basic_rate', 15, 2)->default(0);
            $table->decimal('old_rate', 15, 2)->default(0);
            $table->date('effective_date')->nullable();
            $table->string('tax_no')->nullable();
            $table->string('chrono_no')->nullable();
            $table->string('rest_day', 50)->nullable();
            $table->string('half_day', 50)->nullable();
            $table->string('shift_code', 50)->nullable();
            $table->decimal('hours_per_day', 8, 2)->nullable();
            $table->date('date_terminated')->nullable()->index();
            $table->string('emp_shift', 50)->nullable();
            $table->decimal('max_sl', 8, 2)->nullable();
            $table->decimal('max_vl', 8, 2)->nullable();
            $table->decimal('new_sl', 8, 2)->nullable();
            $table->decimal('new_vl', 8, 2)->nullable();
            $table->decimal('meals', 12, 2)->nullable();
            $table->decimal('transpo', 12, 2)->nullable();
            $table->decimal('bonus', 12, 2)->nullable();
            $table->string('religion', 100)->nullable();
            $table->string('education', 100)->nullable();
            $table->string('hk', 50)->nullable();
            $table->string('level', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->string('no_astek')->nullable();
            $table->string('contract', 100)->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
