<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneySourceTransfer extends Model
{
    protected $fillable = [
        'from_money_source_id',
        'to_money_source_id',
        'branch_id',
        'amount',
        'transfer_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'transfer_date' => 'date',
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
}
