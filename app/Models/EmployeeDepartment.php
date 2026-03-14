<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDepartment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'employee_departments';

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function employees()
    {
        return $this->hasMany(\App\Models\Employee::class, 'employee_department_id', 'id');
    }
}
