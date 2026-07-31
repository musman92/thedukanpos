<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    public const PAYMENT_STATUSES = ['pending', 'partial', 'paid'];

    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'purchase_date',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'paid_amount',
        'returned_amount',
        'payment_status',
        'money_source_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'returned_amount' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function moneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total - (float) $this->returned_amount - (float) $this->paid_amount);
    }

    public function netAmount(): float
    {
        return max(0, (float) $this->total - (float) $this->returned_amount);
    }

    public function refreshPaymentStatus(): void
    {
        $due = $this->balanceDue();
        $paid = (float) $this->paid_amount;

        if ($due <= 0.0001) {
            $status = 'paid';
        } elseif ($paid > 0.0001) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        if ($this->payment_status !== $status) {
            $this->update(['payment_status' => $status]);
        }
    }
}
