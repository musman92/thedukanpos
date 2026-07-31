<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PayrollRun extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'period_start',
        'period_end',
        'status',
        'employee_count',
        'gross_total',
        'deduction_total',
        'net_total',
        'notes',
        'generated_by',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_total' => 'decimal:2',
            'deduction_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'PR-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }
}
