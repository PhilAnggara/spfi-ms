<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'employees';

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'employee_department_id' => 'integer',
            'date_of_birth' => 'date',
            'date_hired' => 'date',
            'effective_date' => 'date',
            'date_terminated' => 'date',
            'basic_rate' => 'decimal:2',
            'old_rate' => 'decimal:2',
            'hours_per_day' => 'decimal:2',
            'max_sl' => 'decimal:2',
            'max_vl' => 'decimal:2',
            'new_sl' => 'decimal:2',
            'new_vl' => 'decimal:2',
            'meals' => 'decimal:2',
            'transpo' => 'decimal:2',
            'bonus' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\EmployeeDepartment::class, 'employee_department_id', 'id');
    }

    public function getEmploymentStatusAttribute(): string
    {
        if (! $this->date_terminated) {
            return 'Active';
        }

        return $this->date_terminated->isFuture() ? 'Active' : 'Terminated';
    }

    public function getDisplayCodeAttribute(): string
    {
        return (string) ($this->code_employee ?: $this->employee_id);
    }

    public function getPhotoUrlAttribute(): string
    {
        $defaultPath = 'assets/images/employee-default.svg';

        if (! $this->photo_path) {
            return asset($defaultPath);
        }

        $absolutePath = public_path($this->photo_path);

        return File::exists($absolutePath)
            ? asset($this->photo_path)
            : asset($defaultPath);
    }
}
