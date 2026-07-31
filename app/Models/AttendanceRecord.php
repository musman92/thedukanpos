<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'attendance_date',
        'clock_in',
        'clock_out',
        'break_minutes',
        'break_started_at',
        'worked_minutes',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'break_started_at' => 'datetime',
            'break_minutes' => 'integer',
            'worked_minutes' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOnBreak(): bool
    {
        return $this->break_started_at !== null && $this->clock_out === null;
    }

    public function isOpenShift(): bool
    {
        return $this->status === 'present'
            && $this->clock_in !== null
            && $this->clock_out === null;
    }

    /**
     * @return array{worked:int}
     */
    public static function calculateWorkedMinutes(
        ?string $clockIn,
        ?string $clockOut,
        int $breakMinutes = 0,
        string $status = 'present',
    ): array {
        if ($status !== 'present' || ! $clockIn || ! $clockOut) {
            return ['worked' => 0];
        }

        $start = \Carbon\Carbon::parse($clockIn);
        $end = \Carbon\Carbon::parse($clockOut);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $total = max(0, (int) $start->diffInMinutes($end));

        return ['worked' => max(0, $total - max(0, $breakMinutes))];
    }
}
