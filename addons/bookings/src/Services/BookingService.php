<?php

namespace Addons\Bookings\Services;

use Addons\Bookings\Models\Booking;
use Addons\Bookings\Models\BookingProductSetting;
use Addons\Bookings\Models\BookingSetting;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Support\BranchContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        protected GoogleCalendarService $google,
        protected SaleService $sales,
        protected BookingMailService $mail,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paginate(array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $assignee = trim((string) ($filters['assignee'] ?? ''));
        $perPage = resolve_page_limit($filters['per_page'] ?? null, company_page_limit());

        $bookings = $this->filteredQuery($filters)
            ->when($from, fn ($query) => $query->whereDate('scheduled_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('scheduled_at', '<=', $to))
            ->orderByDesc('scheduled_at')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'bookings' => $bookings,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'assignee' => $assignee,
                'per_page' => $perPage,
                'company_page_limit' => company_page_limit(),
            ],
            'services' => $this->bookableServices(),
            'settings' => BookingSetting::current(),
        ];
    }

    /**
     * Admin list + optional month calendar payload.
     *
     * @return array<string, mixed>
     */
    public function adminIndex(array $filters = []): array
    {
        $payload = $this->paginate($filters);
        $view = ($filters['view'] ?? '') === 'calendar' ? 'calendar' : 'list';
        $month = $this->resolveMonth($filters['month'] ?? null);

        $payload['filters']['view'] = $view;
        $payload['filters']['month'] = $month;
        $payload['calendar'] = $view === 'calendar'
            ? $this->calendar([...$filters, 'month' => $month])
            : null;
        $payload['assignees'] = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $payload['branches'] = $branches->count() > 1 ? $branches->values() : [];
        $payload['default_branch_id'] = BranchContext::ensure()->id;

        return $payload;
    }

    /**
     * Bookings that fall on the visible month grid (including overflow days).
     *
     * @return array<string, mixed>
     */
    public function calendar(array $filters = []): array
    {
        $timezone = $this->companyTimezone();
        $month = $this->resolveMonth($filters['month'] ?? null);
        $weekStartsOn = $this->weekStartsOn();
        [$gridStart, $gridEnd] = $this->monthGrid($month, $timezone, $weekStartsOn);
        $storeTz = (string) config('app.timezone');

        $items = $this->filteredQuery($filters)
            ->where('scheduled_at', '>=', $gridStart->copy()->timezone($storeTz))
            ->where('scheduled_at', '<=', $gridEnd->copy()->timezone($storeTz))
            ->orderBy('scheduled_at')
            ->limit(500)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'number' => $booking->number,
                'product_id' => $booking->product_id,
                'sale_id' => $booking->sale_id,
                'assigned_user_id' => $booking->assigned_user_id,
                'branch_id' => $booking->branch_id,
                'assignee_name' => $booking->assignee?->name,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'address' => $booking->address,
                'notes' => $booking->notes,
                'service_name' => $booking->service_name,
                'status' => $booking->status,
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                'ends_at' => $booking->ends_at?->toIso8601String(),
                'date' => $booking->scheduled_at?->copy()->timezone($timezone)->toDateString(),
            ])
            ->values()
            ->all();

        $days = [];
        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $days[] = $cursor->toDateString();
        }

        return [
            'month' => $month,
            'week_starts_on' => $weekStartsOn,
            'days' => $days,
            'items' => $items,
        ];
    }

    public function create(array $data): Booking
    {
        $product = Product::query()->where('kind', 'service')->where('is_active', true)->findOrFail($data['product_id']);
        $service = BookingProductSetting::query()
            ->where('product_id', $product->id)
            ->where('is_bookable', true)
            ->firstOrFail();
        $settings = BookingSetting::current();
        $start = $this->parseScheduled($data['scheduled_at']);
        $duration = $service->duration_minutes ?: $settings->default_duration_minutes;
        $end = $start->copy()->addMinutes($duration);
        $public = ($data['source'] ?? 'staff') === 'public';

        if ($public && ! $settings->public_enabled) {
            throw ValidationException::withMessages(['booking' => 'Public booking is currently disabled.']);
        }
        if ($public) {
            $this->assertInsideBusinessHours($start, $end, $settings);
        }
        if ($service->requires_address && blank($data['address'] ?? null)) {
            throw ValidationException::withMessages(['address' => 'Address is required for this service.']);
        }

        $booking = DB::transaction(function () use ($data, $product, $service, $settings, $start, $end, $duration, $public) {
            BookingSetting::query()->whereKey($settings->id)->lockForUpdate()->first();
            $enforceCapacity = $public
                ? $settings->public_time_mode !== 'request'
                : ! $settings->allow_staff_overbook;
            $this->assertAvailable($start, $end, null, $enforceCapacity);
            $assigneeId = filled($data['assigned_user_id'] ?? null) ? (int) $data['assigned_user_id'] : null;
            $this->assertAssigneeFree($start, $end, $assigneeId, null);

            $customer = $this->resolveCustomer($data);
            $this->rememberCustomerEmail($customer, $data['customer_email'] ?? null);

            return Booking::query()->create([
                'number' => 'BKG-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'product_id' => $product->id,
                'customer_id' => $customer->id,
                'assigned_user_id' => $assigneeId,
                'branch_id' => filled($data['branch_id'] ?? null)
                    ? (int) $data['branch_id']
                    : BranchContext::ensure()->id,
                'customer_name' => trim((string) $data['customer_name']),
                'customer_phone' => trim((string) $data['customer_phone']),
                'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'service_name' => $product->name,
                'price_mode' => $service->price_mode,
                'price' => $product->sale_price,
                'duration_minutes' => $duration,
                'scheduled_at' => $start,
                'ends_at' => $end,
                'status' => $data['status'] ?? 'pending',
                'source' => $data['source'] ?? 'staff',
            ]);
        });

        $booking = $booking->fresh(['product', 'customer', 'assignee', 'branch', 'sale']);
        $this->syncGoogle($booking);
        $this->mail->notifyCreated($booking);

        return $booking;
    }

    public function update(Booking $booking, array $data): Booking
    {
        $previousStatus = (string) $booking->status;
        $previousAssignee = $booking->assigned_user_id ? (int) $booking->assigned_user_id : null;
        $settings = BookingSetting::current();
        $start = isset($data['scheduled_at'])
            ? $this->parseScheduled($data['scheduled_at'])
            : $booking->scheduled_at->copy();
        $end = $start->copy()->addMinutes($booking->duration_minutes);

        DB::transaction(function () use ($booking, $data, $settings, $start, $end) {
            BookingSetting::query()->whereKey($settings->id)->lockForUpdate()->first();
            $nextStatus = $data['status'] ?? $booking->status;
            $assigneeId = array_key_exists('assigned_user_id', $data)
                ? (filled($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null)
                : $booking->assigned_user_id;

            // Re-check the slot only when it needs to hold again: restoring a cancelled
            // booking, or moving the time or person. Notes-only edits must always save.
            $restoring = $booking->status === 'cancelled' && $nextStatus !== 'cancelled';
            $moving = $start->ne($booking->scheduled_at) || $assigneeId !== $booking->assigned_user_id;

            if ($nextStatus !== 'cancelled' && ($restoring || $moving)) {
                $this->assertAvailable($start, $end, $booking->id, ! $settings->allow_staff_overbook);
                $this->assertAssigneeFree($start, $end, $assigneeId, $booking->id);
            }

            $booking->update([
                ...$data,
                'scheduled_at' => $start,
                'ends_at' => $end,
            ]);
        });

        $booking = $booking->fresh(['product', 'customer', 'assignee', 'branch', 'sale']);
        $this->syncGoogle($booking);
        $this->mail->notifyUpdated($booking, $previousStatus, $previousAssignee);

        return $booking;
    }

    public function delete(Booking $booking): void
    {
        if ($booking->sale_id) {
            throw ValidationException::withMessages(['booking' => 'A booking linked to an order cannot be deleted.']);
        }

        $booking->update(['status' => 'cancelled']);
        $this->syncGoogle($booking);
        $booking->delete();
    }

    /**
     * Pending and confirmed bookings expire 12 hours after their start time.
     */
    public function expirePast(): int
    {
        $cutoff = Carbon::now()->subHours(12);
        $count = 0;

        Booking::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (Booking $booking) use (&$count) {
                $booking->update(['status' => 'expired']);
                $this->syncGoogle($booking);
                $count++;
            });

        return $count;
    }

    /**
     * Open the booking's order, creating a draft the first time.
     *
     * A draft does not take payment or stock. Checkout on the order screen finishes it.
     */
    public function openOrder(Booking $booking): Sale
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'booking' => 'A cancelled booking cannot be turned into an order.',
                ]);
            }

            $existing = $locked->sale_id ? Sale::query()->find($locked->sale_id) : null;
            if ($existing && $existing->status !== Sale::STATUS_VOID) {
                return $existing;
            }

            $product = $locked->product_id
                ? Product::query()->with(['variants', 'tax'])->find($locked->product_id)
                : null;
            if (! $product) {
                throw ValidationException::withMessages([
                    'booking' => 'The service for this booking was deleted. Create the order from Orders instead.',
                ]);
            }
            $variant = $product->variants->first();
            if (! $variant) {
                throw ValidationException::withMessages([
                    'booking' => "{$product->name} has no price set up. Add a variant to the product first.",
                ]);
            }

            $branch = BranchContext::ensure();
            $sale = $this->sales->park([
                'branch_id' => $branch->id,
                'customer_id' => $locked->customer_id,
                'source' => 'bookings',
                'notes' => "Booking {$locked->number}".($locked->notes ? "\n{$locked->notes}" : ''),
                'items' => [[
                    'variant_id' => $variant->id,
                    'unit_id' => $variant->sale_unit_id,
                    'quantity' => 1,
                    'unit_price' => (float) ($locked->price ?? $product->sale_price),
                    'discount' => 0,
                ]],
            ], false);

            $locked->update(['sale_id' => $sale->id]);

            return $sale;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookableServices(): array
    {
        return BookingProductSetting::query()
            ->where('is_bookable', true)
            ->with(['product' => fn ($query) => $query->where('kind', 'service')->where('is_active', true)])
            ->get()
            ->filter(fn (BookingProductSetting $setting) => $setting->product !== null)
            ->map(fn (BookingProductSetting $setting) => [
                'id' => $setting->product->id,
                'name' => $setting->product->name,
                'description' => $setting->product->notes,
                'image_url' => $setting->product->image_url,
                'price' => (float) $setting->product->sale_price,
                'price_mode' => $setting->price_mode,
                'requires_address' => $setting->requires_address,
                'duration_minutes' => $setting->duration_minutes ?: BookingSetting::current()->default_duration_minutes,
            ])
            ->values()
            ->all();
    }

    /**
     * Open public times for the next 21 days, keyed by service id then date.
     *
     * A time is included only when the shop still has capacity, counting
     * existing bookings and Google Calendar busy time.
     *
     * @return array<string, array<string, list<string>>>
     */
    public function openSlotsByService(): array
    {
        $settings = BookingSetting::current();
        $timezone = $this->companyTimezone();
        $windowStart = Carbon::now($timezone)->startOfDay();
        $windowEnd = $windowStart->copy()->addDays(21)->endOfDay();
        $lead = Carbon::now($timezone)->addMinutes(15);
        $storeStart = $windowStart->copy()->timezone((string) config('app.timezone'));
        $storeEnd = $windowEnd->copy()->timezone((string) config('app.timezone'));

        $periods = Booking::query()
            ->whereIn('status', Booking::HOLDING_STATUSES)
            ->where('scheduled_at', '<', $storeEnd)
            ->where('ends_at', '>', $storeStart)
            ->get(['scheduled_at', 'ends_at'])
            ->map(fn (Booking $booking) => [
                'start' => $booking->scheduled_at,
                'end' => $booking->ends_at,
            ])
            ->concat($this->google->busyPeriods($storeStart, $storeEnd, false))
            ->values();

        $slots = [];
        foreach ($this->bookableServices() as $service) {
            $duration = max(5, (int) $service['duration_minutes']);
            $step = max(15, $duration);
            $byDate = [];

            for ($day = 0; $day < 21; $day++) {
                $date = $windowStart->copy()->addDays($day);
                $hours = $settings->business_hours[strtolower($date->format('l'))] ?? null;
                if (! $hours || ! ($hours['enabled'] ?? false)) {
                    continue;
                }

                $open = $date->copy()->setTimeFromTimeString($hours['open']);
                $close = $date->copy()->setTimeFromTimeString($hours['close']);
                $times = [];
                for ($cursor = $open->copy(); $cursor->copy()->addMinutes($duration)->lte($close); $cursor->addMinutes($step)) {
                    if ($cursor->lt($lead)) {
                        continue;
                    }
                    $end = $cursor->copy()->addMinutes($duration);
                    if ((int) $settings->max_concurrent !== 0 && $this->occupancyPeak($periods, $cursor, $end) >= (int) $settings->max_concurrent) {
                        continue;
                    }
                    $times[] = $cursor->format('H:i');
                }

                if ($times !== []) {
                    $byDate[$date->toDateString()] = $times;
                }
            }

            $slots[(string) $service['id']] = $byDate;
        }

        return $slots;
    }

    /**
     * Active branches for the public page. Empty when the shop has only one.
     *
     * @return list<array{id: int, name: string}>
     */
    public function publicBranches(): array
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        if ($branches->count() < 2) {
            return [];
        }

        return $branches
            ->map(fn (Branch $branch) => ['id' => $branch->id, 'name' => $branch->name])
            ->all();
    }

    public function settings(): BookingSetting
    {
        return BookingSetting::current();
    }

    public function updateSettings(array $data): BookingSetting
    {
        $settings = BookingSetting::current();
        $settings->update($data);

        return $settings->refresh();
    }

    private function assertAvailable(Carbon $start, Carbon $end, ?int $ignoreId, bool $enforce): void
    {
        if (! $enforce) {
            return;
        }

        $capacity = BookingSetting::current()->max_concurrent;
        if ($capacity === 0) {
            return;
        }

        $periods = Booking::query()
            ->whereIn('status', Booking::HOLDING_STATUSES)
            ->where('scheduled_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['scheduled_at', 'ends_at'])
            ->map(fn (Booking $booking) => [
                'start' => $booking->scheduled_at,
                'end' => $booking->ends_at,
            ])
            ->concat($this->google->busyPeriods($start, $end))
            ->values();

        if ($this->occupancyPeak($periods, $start, $end) >= $capacity) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'That time is no longer available. Please choose another slot.',
            ]);
        }
    }

    /**
     * @param  iterable<int, array{start: Carbon, end: Carbon}>  $periods
     */
    private function occupancyPeak(iterable $periods, Carbon $start, Carbon $end): int
    {
        $periods = collect($periods)->values();
        $checkpoints = $periods
            ->pluck('start')
            ->filter(fn (Carbon $periodStart) => $periodStart->gte($start) && $periodStart->lt($end))
            ->prepend($start);

        return (int) ($checkpoints->max(
            fn (Carbon $point) => $periods->filter(
                fn (array $period) => $period['start']->lte($point) && $period['end']->gt($point),
            )->count(),
        ) ?? 0);
    }

    private function assertAssigneeFree(Carbon $start, Carbon $end, ?int $userId, ?int $ignoreId): void
    {
        if (! $userId) {
            return;
        }

        $busy = Booking::query()
            ->where('assigned_user_id', $userId)
            ->whereIn('status', Booking::HOLDING_STATUSES)
            ->where('scheduled_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if (! $busy) {
            return;
        }

        $name = User::query()->whereKey($userId)->value('name') ?: 'This person';

        throw ValidationException::withMessages([
            'assigned_user_id' => "{$name} already has a booking at that time.",
        ]);
    }

    private function assertInsideBusinessHours(Carbon $start, Carbon $end, BookingSetting $settings): void
    {
        $timezone = (string) (company_settings()['timezone'] ?? tenant('timezone') ?? config('app.timezone'));
        $localStart = $start->copy()->setTimezone($timezone);
        $localEnd = $end->copy()->setTimezone($timezone);
        $day = strtolower($localStart->format('l'));
        $hours = $settings->business_hours[$day] ?? null;
        if (! $hours || ! ($hours['enabled'] ?? false)) {
            throw ValidationException::withMessages(['scheduled_at' => 'The business is closed on that day.']);
        }

        $open = $localStart->copy()->setTimeFromTimeString($hours['open']);
        $close = $localStart->copy()->setTimeFromTimeString($hours['close']);
        if ($localStart->lt($open) || $localEnd->gt($close)) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a time inside business hours.']);
        }
    }

    private function parseScheduled(mixed $value): Carbon
    {
        $timezone = (string) (company_settings()['timezone'] ?? tenant('timezone') ?? config('app.timezone'));

        return Carbon::parse($value, $timezone)->setTimezone(config('app.timezone'));
    }

    private function filteredQuery(array $filters)
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $assignee = trim((string) ($filters['assignee'] ?? ''));

        return Booking::query()
            ->with([
                'product:id,name,kind,sale_price,tax_id',
                'product.tax:id,name,rate,is_inclusive',
                'customer:id,name,phone',
                'assignee:id,name',
                'branch:id,name',
                'sale:id,number,status',
            ])
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('service_name', 'like', "%{$q}%");
            }))
            ->when(in_array($status, Booking::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($assignee === 'none', fn ($query) => $query->whereNull('assigned_user_id'))
            ->when(ctype_digit($assignee), fn ($query) => $query->where('assigned_user_id', (int) $assignee));
    }

    private function companyTimezone(): string
    {
        return (string) (company_settings()['timezone'] ?? tenant('timezone') ?? config('app.timezone'));
    }

    private function weekStartsOn(): string
    {
        $day = strtolower((string) (company_settings()['week_starts_on'] ?? 'monday'));

        return in_array($day, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true)
            ? $day
            : 'monday';
    }

    private function resolveMonth(mixed $value): string
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return $value;
        }

        return now($this->companyTimezone())->format('Y-m');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthGrid(string $month, string $timezone, string $weekStartsOn): array
    {
        $weekStart = match ($weekStartsOn) {
            'sunday' => Carbon::SUNDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            default => Carbon::MONDAY,
        };

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01', $timezone)->startOfDay();
        $weekEnd = ($weekStart + 6) % 7;
        $gridStart = $start->copy()->startOfWeek($weekStart);
        $gridEnd = $start->copy()->endOfMonth()->endOfWeek($weekEnd, $weekStart)->endOfDay();

        return [$gridStart, $gridEnd];
    }

    private function resolveCustomer(array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->findOrFail($data['customer_id']);
        }

        $phone = trim((string) $data['customer_phone']);
        $existing = Customer::query()->where('phone', $phone)->first();
        if ($existing) {
            return $existing;
        }

        return Customer::query()->create([
            'name' => trim((string) $data['customer_name']),
            'code' => Customer::resolveCode(null),
            'phone' => $phone,
            'email' => $this->cleanEmail($data['customer_email'] ?? null),
            'address' => $data['address'] ?? null,
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function rememberCustomerEmail(Customer $customer, mixed $email): void
    {
        $email = $this->cleanEmail($email);
        if ($email && blank($customer->email)) {
            $customer->update(['email' => $email]);
        }
    }

    private function cleanEmail(mixed $email): ?string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function syncGoogle(Booking $booking): void
    {
        try {
            $this->google->sync($booking);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
