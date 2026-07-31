<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftMoneySource extends Model
{
    protected $fillable = [
        'shift_id',
        'money_source_id',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:4',
            'closing_balance' => 'decimal:4',
            'expected_balance' => 'decimal:4',
            'difference' => 'decimal:4',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function moneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class);
    }
}
