<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'employee_number',
        'designation',
        'department',
        'hire_date',
        'employment_status',
        'pay_frequency',
        'pay_rate',
        'phone',
        'address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'pay_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', 'active');
    }
}
