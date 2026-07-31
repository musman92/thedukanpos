<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneySourceFundMovement extends Model
{
    public const TYPE_OWNER_WITHDRAWAL = 'owner_withdrawal';

    protected $fillable = [
        'branch_id',
        'from_money_source_id',
        'to_money_source_id',
        'movement_type',
        'amount',
        'movement_date',
        'notes',
        'created_by',
        'shift_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'movement_date' => 'date',
        ];
    }

    public function fromMoneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class, 'from_money_source_id');
    }

    public function toMoneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class, 'to_money_source_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
