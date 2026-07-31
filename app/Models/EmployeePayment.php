<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePayment extends Model
{
    use SoftDeletes;

    public const KINDS = ['payroll', 'wage', 'advance', 'bonus'];

    public const KIND_LABELS = [
        'payroll' => 'Payroll payment',
        'wage' => 'Direct wage / salary',
        'advance' => 'Advance',
        'bonus' => 'Bonus paid now',
    ];

    protected $fillable = [
        'user_id',
        'payroll_item_id',
        'money_source_id',
        'branch_id',
        'kind',
        'amount',
        'payment_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'payment_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function moneySource(): BelongsTo
    {
        return $this->belongsTo(MoneySource::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function kindLabel(string $kind): string
    {
        return self::KIND_LABELS[$kind] ?? ucfirst($kind);
    }
}
