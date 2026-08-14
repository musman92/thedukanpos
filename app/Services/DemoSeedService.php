<?php

namespace App\Services;

use App\Jobs\SeedTenantDemoData;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MoneySource;
use App\Models\PayrollItem;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\ProductVariant;
use App\Models\Rack;
use App\Models\Section;
use App\Models\Shift;
use App\Models\ShiftMoneySource;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationOption;
use App\Support\BranchContext;
use App\Support\TenantDefaultRoles;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

class DemoSeedService
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    private const HISTORY_DAYS = 60;

    public function __construct(
        protected PurchaseService $purchases,
        protected SaleService $sales,
        protected ExpenseService $expenses,
        protected FinanceService $finance,
        protected InventoryService $inventory,
        protected UserService $users,
        protected AttendanceService $attendance,
        protected LeaveService $leaves,
        protected PayrollAdjustmentService $payrollAdjustments,
        protected HrService $hr,
        protected EmployeePaymentService $employeePayments,
    ) {}

    /**
     * @return array{status:?string, message:?string, updated_at:?string, initiated_by:?int}
     */
    public function status(Tenant $tenant): array
    {
        $payload = $tenant->demo_seed;
        if (! is_array($payload)) {
            return [
                'status' => null,
                'message' => null,
                'updated_at' => null,
                'initiated_by' => null,
            ];
        }

        return [
            'status' => $payload['status'] ?? null,
            'message' => $payload['message'] ?? null,
            'updated_at' => $payload['updated_at'] ?? null,
            'initiated_by' => isset($payload['initiated_by']) ? (int) $payload['initiated_by'] : null,
        ];
    }

    public function isInProgress(Tenant $tenant): bool
    {
        return in_array($this->status($tenant)['status'] ?? null, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function markStatus(
        Tenant $tenant,
        string $status,
        string $message,
        ?int $initiatedBy = null,
    ): void {
        $existing = is_array($tenant->demo_seed) ? $tenant->demo_seed : [];

        $tenant->forceFill([
            'demo_seed' => [
                'status' => $status,
                'message' => $message,
                'updated_at' => now()->toIso8601String(),
                'initiated_by' => $initiatedBy ?? ($existing['initiated_by'] ?? null),
            ],
        ])->save();
    }

    /**
     * Queue seed without blocking the HTTP request.
     * Prefer afterResponse (works under php-fpm). Async queue connections use a worker.
     */
    public function dispatch(Tenant $tenant, ?int $initiatedBy = null, bool $force = false): void
    {
        if (! $tenant->is_demo) {
            throw new \InvalidArgumentException('Tenant is not marked as demo.');
        }

        if ($this->isInProgress($tenant) && ! $force && ! $this->isStale($tenant)) {
            throw new \RuntimeException('A demo seed is already queued or running for this tenant.');
        }

        $this->markStatus(
            $tenant,
            self::STATUS_QUEUED,
            'Demo seed queued — starting shortly after this page responds.',
            $initiatedBy,
        );

        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            // Runs in the same PHP process after the HTTP response is sent (no shell/nohup).
            SeedTenantDemoData::dispatch($tenant->id, $initiatedBy)->afterResponse();

            return;
        }

        SeedTenantDemoData::dispatch($tenant->id, $initiatedBy);
    }

    public function isStale(Tenant $tenant): bool
    {
        $status = $this->status($tenant);
        if (! in_array($status['status'] ?? null, [self::STATUS_QUEUED, self::STATUS_RUNNING], true)) {
            return false;
        }

        $updatedAt = $status['updated_at'] ?? null;
        if (! $updatedAt) {
            return true;
        }

        try {
            return Carbon::parse($updatedAt)->lt(now()->subMinutes(5));
        } catch (Throwable) {
            return true;
        }
    }

    public function run(Tenant $tenant, ?int $initiatedBy = null): void
    {
        if (! $tenant->is_demo) {
            throw new \InvalidArgumentException('Tenant is not marked as demo.');
        }

        set_time_limit(0);
        $this->markStatus($tenant, self::STATUS_RUNNING, 'Wiping and seeding demo data…', $initiatedBy);

        try {
            $tenant->run(function () {
                $this->wipeTransactionalData();
                $this->seedDemoData();
            });

            $this->markStatus(
                $tenant->fresh(),
                self::STATUS_COMPLETED,
                'Demo data ready: 2 branches and ~'.self::HISTORY_DAYS.' days of purchases, sales, and expenses.',
                $initiatedBy,
            );
        } catch (Throwable $e) {
            Log::error('Demo seed failed', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markStatus(
                $tenant->fresh() ?? $tenant,
                self::STATUS_FAILED,
                $e->getMessage(),
                $initiatedBy,
            );

            throw $e;
        } finally {
            Carbon::setTestNow();
            Auth::logout();
        }
    }

    protected function spawnBackgroundProcess(Tenant $tenant, ?int $initiatedBy): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/demo-seed-'.$tenant->id.'.log');

        @file_put_contents(
            $logFile,
            '['.now()->toDateTimeString()."] Spawning: {$php} {$artisan} dukan:seed-demo {$tenant->id}\n",
            FILE_APPEND
        );

        $arguments = [
            $php,
            $artisan,
            'dukan:seed-demo',
            $tenant->id,
        ];
        if ($initiatedBy) {
            $arguments[] = '--initiated-by='.$initiatedBy;
        }

        $escaped = implode(' ', array_map('escapeshellarg', $arguments));
        $cwd = escapeshellarg(base_path());
        $log = escapeshellarg($logFile);

        // Avoid nohup (fails under php-fpm: "can't detach from console").
        if (DIRECTORY_SEPARATOR === '\\') {
            pclose(popen('start /B '.$escaped.' >> '.$log.' 2>&1', 'r'));

            return;
        }

        exec('cd '.$cwd.' && '.$escaped.' >> '.$log.' 2>&1 < /dev/null &');
    }

    protected function wipeTransactionalData(): void
    {
        $tables = [
            'customer_payment_sale',
            'supplier_payment_purchase',
            'quotation_items',
            'quotations',
            'stock_damage_items',
            'stock_damages',
            'sale_return_items',
            'sale_returns',
            'purchase_return_items',
            'purchase_returns',
            'stock_transfer_items',
            'stock_transfers',
            'stock_adjustment_items',
            'stock_adjustments',
            'sale_payments',
            'sale_items',
            'sales',
            'purchase_items',
            'purchases',
            'customer_payments',
            'supplier_payments',
            'employee_payments',
            'payroll_adjustments',
            'payroll_items',
            'payroll_runs',
            'leave_requests',
            'attendance_records',
            'employee_profiles',
            'ledger_transactions',
            'money_source_transfers',
            'money_source_fund_movements',
            'shift_money_sources',
            'shifts',
            'stock_movements',
            'branch_stocks',
            'serial_numbers',
            'product_locations',
            'product_variants',
            'products',
            'variation_options',
            'variations',
            'racks',
            'sections',
            'brands',
            'categories',
            'customers',
            'suppliers',
            'activity_logs',
        ];

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Drop demo staff (keep platform-seeded admin).
        User::query()
            ->where('username', '!=', 'admin')
            ->orderByDesc('id')
            ->each(function (User $user) {
                $user->branches()->detach();
                $user->syncRoles([]);
                $user->delete();
            });

        // Reset party / till balances; keep source rows.
        MoneySource::query()->update([
            'opening_balance' => 0,
            'balance' => 0,
        ]);

        // Restore day-one masters wiped above (General category, Walk-in, etc.).
        app(TenantBootstrapService::class)->seedDayOneMasters();
    }

    protected function seedDemoData(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        Auth::login($admin);

        $this->finance->seedDefaultAccounts();

        $pcs = Unit::query()->where('code', 'pcs')->firstOrFail();
        $tax = Tax::query()->where('code', 'gst18')->first()
            ?? Tax::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        $cash = MoneySource::query()->where('code', 'cash')->firstOrFail();
        $card = MoneySource::query()->firstOrCreate(
            ['code' => 'card'],
            [
                'name' => 'Card',
                'type' => 'BANK',
                'opening_balance' => 0,
                'balance' => 0,
                'is_active' => true,
                'exclude_from_dashboard_profit' => false,
                'is_system' => false,
            ],
        );

        foreach ([$cash, $card] as $source) {
            $source->update([
                'opening_balance' => 2500000,
                'balance' => 2500000,
                'is_active' => true,
            ]);
        }

        $branches = $this->ensureBranches($admin);
        $branchIds = array_map(fn (Branch $b) => $b->id, $branches);

        foreach ([$cash, $card] as $source) {
            $source->branches()->syncWithoutDetaching($branchIds);
        }

        $staff = $this->seedStaff($branches);
        $cashiersByBranch = [];
        foreach ($staff as $member) {
            if ($member['can_login']) {
                $cashiersByBranch[$member['branch_id']][] = $member['user'];
            }
        }

        $brands = $this->seedBrands();
        $categories = $this->seedCategories($tax->id);
        $placements = $this->seedSectionsAndRacks();
        $variations = $this->seedVariations();
        $variants = $this->seedProducts(
            brands: $brands,
            categories: $categories,
            unitId: $pcs->id,
            taxId: $tax->id,
            branches: $branches,
            placements: $placements,
            variations: $variations,
        );
        $customers = $this->seedCustomers();
        $suppliers = $this->seedSuppliers();

        $start = now()->subDays(self::HISTORY_DAYS)->startOfDay();
        $end = now()->startOfDay();

        // Opening stock: one purchase per supplier per branch (mixed suppliers).
        foreach ($branches as $branch) {
            BranchContext::set($branch->id);
            $chunks = array_values(array_chunk($variants, max(1, (int) ceil(count($variants) / count($suppliers)))));
            foreach ($suppliers as $supplierIndex => $supplier) {
                $chunk = $chunks[$supplierIndex] ?? $variants;
                $this->seedPurchase(
                    branch: $branch,
                    supplier: $supplier,
                    variants: $chunk,
                    date: $start->copy(),
                    multiplier: 8,
                    hour: 8 + $supplierIndex,
                );
            }
        }

        $expenseAccounts = Account::query()
            ->where('type', 'expense')
            ->where('is_active', true)
            ->whereRaw('LOWER(name) != ?', ['salary'])
            ->whereRaw('LOWER(name) != ?', ['purchase'])
            ->orderBy('id')
            ->get();

        $day = $start->copy()->addDay();

        while ($day->lte($end)) {
            Carbon::setTestNow($day->copy()->setTime(10, 0));

            foreach ($branches as $branch) {
                BranchContext::set($branch->id);

                $branchCashiers = $cashiersByBranch[$branch->id] ?? [];
                if ($branchCashiers === []) {
                    $branchCashiers = [$admin];
                }
                $opener = $branchCashiers[array_rand($branchCashiers)];

                $shift = $this->openDemoShift(
                    branch: $branch,
                    day: $day->copy(),
                    opener: $opener,
                    cash: $cash,
                    card: $card,
                );

                // Restock mid-week from a rotating mix of suppliers.
                if (in_array((int) $day->dayOfWeek, [Carbon::MONDAY, Carbon::THURSDAY], true)) {
                    $purchaseCount = random_int(1, min(2, count($suppliers)));
                    $pickedSuppliers = collect($suppliers)->shuffle()->take($purchaseCount)->values();
                    foreach ($pickedSuppliers as $purchaseIndex => $supplier) {
                        $this->seedPurchase(
                            branch: $branch,
                            supplier: $supplier,
                            variants: $variants,
                            date: $day->copy(),
                            multiplier: 2,
                            hour: 8 + $purchaseIndex,
                            lineCount: random_int(4, 8),
                        );
                    }
                }

                $salesToday = $day->isWeekend()
                    ? random_int(3, 6)
                    : random_int(5, 10);

                for ($i = 0; $i < $salesToday; $i++) {
                    $hour = random_int(9, 20);
                    $minute = random_int(0, 59);
                    Carbon::setTestNow($day->copy()->setTime($hour, $minute));

                    $this->seedSale(
                        branch: $branch,
                        variants: $variants,
                        customers: $customers,
                        cash: $cash,
                        card: $card,
                        cashier: $branchCashiers[array_rand($branchCashiers)],
                        at: $day->copy()->setTime($hour, $minute),
                        shift: $shift,
                    );
                }

                // Operating expenses a few times per week.
                if ($expenseAccounts->isNotEmpty() && in_array((int) $day->dayOfWeek, [Carbon::TUESDAY, Carbon::FRIDAY], true)) {
                    Carbon::setTestNow($day->copy()->setTime(18, 30));
                    $account = $expenseAccounts->random();
                    try {
                        $this->expenses->create([
                            'account_id' => $account->id,
                            'money_source_id' => $cash->id,
                            'amount' => round(random_int(800, 4500) + (random_int(0, 99) / 100), 2),
                            'expense_date' => $day->toDateString(),
                            'notes' => "Demo {$account->name} — {$branch->name}",
                        ]);
                    } catch (Throwable) {
                        // Skip if till balance is insufficient that day.
                    }
                }

                // Keep today's shifts open for live POS; close historical days.
                if (! $day->isSameDay($end)) {
                    $this->closeDemoShift(
                        shift: $shift,
                        closer: $opener,
                        day: $day->copy(),
                        cash: $cash,
                    );
                }
            }

            if (! $day->isWeekend() || random_int(1, 100) <= 25) {
                $this->seedAttendanceForDay($staff, $day->copy());
            }

            $day->addDay();
        }

        $this->seedLeavesAndPayroll($staff, $start->copy(), $end->copy(), $cash);

        Carbon::setTestNow();
        Auth::login($admin);
        BranchContext::set($branches[0]->id);
        $admin->update(['branch_id' => $branches[0]->id]);
        $admin->branches()->sync(
            collect($branchIds)->mapWithKeys(fn ($id, $i) => [
                $id => ['is_primary' => $i === 0],
            ])->all()
        );
    }

    /**
     * @param  list<Branch>  $branches
     * @return list<array{user:User, branch_id:int, can_login:bool, role:string}>
     */
    protected function seedStaff(array $branches): array
    {
        $main = $branches[0];
        $downtown = $branches[1] ?? $branches[0];

        $roster = [
            [
                'name' => 'Ayesha Manager',
                'username' => 'ayesha',
                'role' => TenantDefaultRoles::MANAGER,
                'branch' => $main,
                'designation' => 'Store Manager',
                'department' => 'Management',
                'pay_rate' => 85000,
                'can_login' => true,
            ],
            [
                'name' => 'Hassan Cashier',
                'username' => 'hassan',
                'role' => TenantDefaultRoles::CASHIER,
                'branch' => $main,
                'designation' => 'Senior Cashier',
                'department' => 'Front Desk',
                'pay_rate' => 45000,
                'can_login' => true,
            ],
            [
                'name' => 'Nadia Cashier',
                'username' => 'nadia',
                'role' => TenantDefaultRoles::CASHIER,
                'branch' => $downtown,
                'designation' => 'Cashier',
                'department' => 'Front Desk',
                'pay_rate' => 42000,
                'can_login' => true,
            ],
            [
                'name' => 'Imran Stock',
                'username' => 'imran',
                'role' => TenantDefaultRoles::CASHIER,
                'branch' => $main,
                'designation' => 'Stock Keeper',
                'department' => 'Warehouse',
                'pay_rate' => 38000,
                'can_login' => true,
            ],
            [
                'name' => 'Sana Sales',
                'username' => 'sana',
                'role' => TenantDefaultRoles::CASHIER,
                'branch' => $downtown,
                'designation' => 'Sales Associate',
                'department' => 'Sales',
                'pay_rate' => 36000,
                'can_login' => true,
            ],
            [
                'name' => 'Usman Helper',
                'username' => 'usmanh',
                'role' => TenantDefaultRoles::CASHIER,
                'branch' => $main,
                'designation' => 'Floor Helper',
                'department' => 'Operations',
                'pay_rate' => 28000,
                'can_login' => true,
            ],
            [
                'name' => 'Rabia Packer',
                'username' => null,
                'role' => null,
                'branch' => $downtown,
                'designation' => 'Packer',
                'department' => 'Warehouse',
                'pay_rate' => 25000,
                'can_login' => false,
            ],
        ];

        $staff = [];
        foreach ($roster as $i => $row) {
            $payload = [
                'name' => $row['name'],
                'branch_id' => $row['branch']->id,
                'can_login' => $row['can_login'],
                'is_active' => true,
                'is_employee' => true,
                'employee_number' => 'E'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'designation' => $row['designation'],
                'department' => $row['department'],
                'hire_date' => now()->subMonths(random_int(4, 18))->toDateString(),
                'employment_status' => 'active',
                'pay_frequency' => 'monthly',
                'pay_rate' => $row['pay_rate'],
                'employee_branch_id' => $row['branch']->id,
                'phone' => '0301'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
            ];

            if ($row['can_login']) {
                $payload['username'] = $row['username'];
                $payload['password'] = 'password';
                $payload['role'] = $row['role'];
                $payload['email'] = $row['username'].'@demo.test';
            }

            $user = $this->users->create($payload);
            $staff[] = [
                'user' => $user,
                'branch_id' => $row['branch']->id,
                'can_login' => $row['can_login'],
                'role' => (string) ($row['role'] ?? ''),
            ];
        }

        return $staff;
    }

    /**
     * @param  list<array{user:User, branch_id:int, can_login:bool, role:string}>  $staff
     */
    protected function seedAttendanceForDay(array $staff, Carbon $day): void
    {
        foreach ($staff as $member) {
            BranchContext::set($member['branch_id']);
            $roll = random_int(1, 100);

            if ($day->isWeekend()) {
                $status = $roll <= 70 ? 'present' : 'holiday';
            } elseif ($roll <= 6) {
                $status = 'absent';
            } elseif ($roll <= 12) {
                $status = 'paid_leave';
            } elseif ($roll <= 15) {
                $status = 'unpaid_leave';
            } else {
                $status = 'present';
            }

            $payload = [
                'user_id' => $member['user']->id,
                'attendance_date' => $day->toDateString(),
                'status' => $status,
                'notes' => 'Demo attendance',
            ];

            if ($status === 'present') {
                $payload['clock_in'] = sprintf('%02d:%02d', random_int(8, 10), random_int(0, 45));
                $payload['clock_out'] = sprintf('%02d:%02d', random_int(17, 20), random_int(0, 50));
            }

            try {
                $this->attendance->mark($payload);
            } catch (Throwable) {
                // Skip duplicates / validation edge cases.
            }
        }
    }

    /**
     * @param  list<array{user:User, branch_id:int, can_login:bool, role:string}>  $staff
     */
    protected function seedLeavesAndPayroll(array $staff, Carbon $start, Carbon $end, MoneySource $cash): void
    {
        if ($staff === []) {
            return;
        }

        $leaveSamples = [
            ['type' => 'casual', 'days' => 1, 'reason' => 'Personal errand'],
            ['type' => 'sick', 'days' => 2, 'reason' => 'Flu / rest'],
            ['type' => 'annual', 'days' => 3, 'reason' => 'Family trip'],
            ['type' => 'unpaid', 'days' => 1, 'reason' => 'Unpaid day off'],
            ['type' => 'casual', 'days' => 1, 'reason' => 'Appointment'],
        ];

        foreach ($leaveSamples as $i => $sample) {
            $member = $staff[$i % count($staff)];
            BranchContext::set($member['branch_id']);
            $leaveStart = $start->copy()->addDays(10 + ($i * 9));
            if ($leaveStart->gt($end)) {
                continue;
            }
            $leaveEnd = $leaveStart->copy()->addDays($sample['days'] - 1);
            if ($leaveEnd->gt($end)) {
                $leaveEnd = $end->copy();
            }

            try {
                Carbon::setTestNow($leaveStart->copy()->setTime(9, 0));
                $leave = $this->leaves->create([
                    'user_id' => $member['user']->id,
                    'leave_type' => $sample['type'],
                    'start_date' => $leaveStart->toDateString(),
                    'end_date' => $leaveEnd->toDateString(),
                    'reason' => $sample['reason'],
                ]);
                $reviewStatus = $i === count($leaveSamples) - 1 ? 'rejected' : 'approved';
                $this->leaves->review(
                    $leave,
                    $reviewStatus,
                    $reviewStatus === 'approved' ? 'Approved for demo' : 'Rejected for demo',
                );
            } catch (Throwable) {
                // ignore
            }
        }

        $cursor = $start->copy()->startOfMonth();
        $months = [];
        while ($cursor->lte($end)) {
            $months[] = [
                'start' => $cursor->copy()->startOfMonth()->toDateString(),
                'end' => $cursor->copy()->endOfMonth()->toDateString(),
            ];
            $cursor->addMonth();
        }

        foreach ($months as $monthIndex => $month) {
            $bonusStaff = collect($staff)->shuffle()->take(random_int(2, min(4, count($staff))));
            foreach ($bonusStaff as $member) {
                try {
                    $this->payrollAdjustments->create([
                        'user_id' => $member['user']->id,
                        'type' => 'bonus',
                        'amount' => random_int(1500, 8000),
                        'effective_date' => Carbon::parse($month['start'])->addDays(random_int(5, 20))->toDateString(),
                        'notes' => 'Demo performance bonus',
                    ]);
                } catch (Throwable) {
                    // ignore
                }
            }

            if ($monthIndex === 0 && isset($staff[1])) {
                try {
                    $this->payrollAdjustments->create([
                        'user_id' => $staff[1]['user']->id,
                        'type' => 'deduction',
                        'amount' => random_int(500, 2000),
                        'effective_date' => Carbon::parse($month['start'])->addDays(12)->toDateString(),
                        'notes' => 'Demo advance recovery',
                    ]);
                } catch (Throwable) {
                    // ignore
                }
            }

            try {
                Carbon::setTestNow(Carbon::parse($month['end'])->setTime(16, 0));
                $run = $this->hr->generatePayrollRun($month['start'], $month['end'], null);
                $run = $this->hr->finalizePayrollRun($run);
                $run->load('items');

                foreach ($run->items as $item) {
                    /** @var PayrollItem $item */
                    $member = collect($staff)->firstWhere(fn ($m) => $m['user']->id === $item->user_id);
                    BranchContext::set($member['branch_id'] ?? BranchContext::ensure()->id);

                    $payAmount = (float) $item->net_pay;
                    if ($payAmount <= 0) {
                        continue;
                    }

                    if ($monthIndex === 0 && $item->user_id === ($staff[0]['user']->id ?? null)) {
                        $payAmount = round($payAmount * 0.6, 2);
                    }

                    try {
                        $this->employeePayments->create([
                            'user_id' => $item->user_id,
                            'money_source_id' => $cash->id,
                            'amount' => $payAmount,
                            'kind' => 'payroll',
                            'payroll_item_id' => $item->id,
                            'payment_date' => Carbon::parse($month['end'])->addDay()->toDateString(),
                            'notes' => 'Demo payroll payment',
                        ]);
                    } catch (Throwable) {
                        // ignore cash shortage
                    }
                }
            } catch (Throwable) {
                // ignore payroll failures
            }
        }

        $adhoc = collect($staff)->shuffle()->take(3)->values();
        foreach ($adhoc as $i => $member) {
            BranchContext::set($member['branch_id']);
            try {
                $this->employeePayments->create([
                    'user_id' => $member['user']->id,
                    'money_source_id' => $cash->id,
                    'amount' => random_int(1000, 5000),
                    'kind' => $i === 0 ? 'advance' : 'bonus',
                    'payment_date' => $start->copy()->addDays(20 + ($i * 7))->toDateString(),
                    'notes' => $i === 0 ? 'Demo salary advance' : 'Demo spot bonus',
                ]);
            } catch (Throwable) {
                // ignore
            }
        }
    }

    /**
     * @return list<Branch>
     */
    protected function ensureBranches(User $admin): array
    {
        $main = Branch::query()->firstOrCreate(
            ['code' => 'main'],
            [
                'name' => 'Main Branch',
                'phone' => '+92-300-1000001',
                'address' => '12 Market Road, City Center',
                'is_active' => true,
            ],
        );
        $main->update([
            'name' => $main->name ?: 'Main Branch',
            'is_active' => true,
        ]);

        $downtown = Branch::query()->firstOrCreate(
            ['code' => 'downtown'],
            [
                'name' => 'Downtown Branch',
                'phone' => '+92-300-1000002',
                'address' => '88 Commercial Avenue, Downtown',
                'is_active' => true,
            ],
        );
        $downtown->update([
            'name' => 'Downtown Branch',
            'phone' => $downtown->phone ?: '+92-300-1000002',
            'address' => $downtown->address ?: '88 Commercial Avenue, Downtown',
            'is_active' => true,
        ]);

        $admin->update(['branch_id' => $main->id]);
        $admin->branches()->syncWithoutDetaching([
            $main->id => ['is_primary' => true],
            $downtown->id => ['is_primary' => false],
        ]);

        return [$main->fresh(), $downtown->fresh()];
    }

    /**
     * @return list<Brand>
     */
    protected function seedBrands(): array
    {
        $rows = [
            ['name' => 'FreshFarm', 'code' => 'FRESH'],
            ['name' => 'DailyMart', 'code' => 'DAILY'],
            ['name' => 'HomeCare', 'code' => 'HOME'],
            ['name' => 'SnackBox', 'code' => 'SNACK'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = Brand::query()->create([
                ...$row,
                'is_active' => true,
            ]);
        }

        return $out;
    }

    /**
     * @return list<Category>
     */
    protected function seedCategories(int $taxId): array
    {
        $rows = [
            ['name' => 'Groceries', 'code' => 'GROC'],
            ['name' => 'Beverages', 'code' => 'BEV'],
            ['name' => 'Snacks', 'code' => 'SNK'],
            ['name' => 'Household', 'code' => 'HH'],
            ['name' => 'Personal Care', 'code' => 'PC'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = Category::query()->create([
                ...$row,
                'default_tax_id' => $taxId,
                'is_active' => true,
            ]);
        }

        return $out;
    }

    /**
     * @return list<array{section_id:int, rack_id:int, label:string}>
     */
    protected function seedSectionsAndRacks(): array
    {
        $layout = [
            [
                'name' => 'Dry Goods',
                'code' => 'DRY',
                'racks' => [
                    ['name' => 'Aisle A1', 'code' => 'A1'],
                    ['name' => 'Aisle A2', 'code' => 'A2'],
                ],
            ],
            [
                'name' => 'Beverages',
                'code' => 'BEVS',
                'racks' => [
                    ['name' => 'Cooler B1', 'code' => 'B1'],
                    ['name' => 'Shelf B2', 'code' => 'B2'],
                ],
            ],
            [
                'name' => 'Snacks & Impulse',
                'code' => 'SNKX',
                'racks' => [
                    ['name' => 'Front C1', 'code' => 'C1'],
                    ['name' => 'Front C2', 'code' => 'C2'],
                ],
            ],
            [
                'name' => 'Household',
                'code' => 'HHLD',
                'racks' => [
                    ['name' => 'Aisle D1', 'code' => 'D1'],
                    ['name' => 'Aisle D2', 'code' => 'D2'],
                ],
            ],
        ];

        $placements = [];
        foreach ($layout as $sectionRow) {
            $section = Section::query()->create([
                'name' => $sectionRow['name'],
                'code' => $sectionRow['code'],
                'is_active' => true,
            ]);

            foreach ($sectionRow['racks'] as $rackRow) {
                $rack = Rack::query()->create([
                    'section_id' => $section->id,
                    'name' => $rackRow['name'],
                    'code' => $rackRow['code'],
                    'is_active' => true,
                ]);
                $placements[] = [
                    'section_id' => $section->id,
                    'rack_id' => $rack->id,
                    'label' => "{$section->name} → {$rack->name}",
                ];
            }
        }

        return $placements;
    }

    /**
     * @return array{size: Variation, pack: Variation}
     */
    protected function seedVariations(): array
    {
        $size = Variation::query()->create([
            'name' => 'Size',
            'code' => 'SIZE',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        foreach ([['Small', 'S'], ['Medium', 'M'], ['Large', 'L']] as $i => [$name, $code]) {
            VariationOption::query()->create([
                'variation_id' => $size->id,
                'name' => $name,
                'code' => $code,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        $pack = Variation::query()->create([
            'name' => 'Pack',
            'code' => 'PACK',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        foreach ([['Single', '1PK'], ['Twin Pack', '2PK'], ['Family Pack', 'FAM']] as $i => [$name, $code]) {
            VariationOption::query()->create([
                'variation_id' => $pack->id,
                'name' => $name,
                'code' => $code,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        $size->load('options');
        $pack->load('options');

        return [
            'size' => $size,
            'pack' => $pack,
        ];
    }

    /**
     * @param  list<Brand>  $brands
     * @param  list<Category>  $categories
     * @param  list<Branch>  $branches
     * @param  list<array{section_id:int, rack_id:int, label:string}>  $placements
     * @param  array{size: Variation, pack: Variation}  $variations
     * @return list<ProductVariant>
     */
    protected function seedProducts(
        array $brands,
        array $categories,
        int $unitId,
        int $taxId,
        array $branches,
        array $placements,
        array $variations,
    ): array {
        $singles = [
            ['name' => 'Basmati Rice 5kg', 'cost' => 950, 'price' => 1250, 'cat' => 0, 'brand' => 0],
            ['name' => 'Cooking Oil 1L', 'cost' => 420, 'price' => 560, 'cat' => 0, 'brand' => 0],
            ['name' => 'Wheat Flour 10kg', 'cost' => 780, 'price' => 990, 'cat' => 0, 'brand' => 1],
            ['name' => 'Sugar 1kg', 'cost' => 140, 'price' => 185, 'cat' => 0, 'brand' => 1],
            ['name' => 'Green Tea 200g', 'cost' => 310, 'price' => 420, 'cat' => 1, 'brand' => 1],
            ['name' => 'Mineral Water 1.5L', 'cost' => 45, 'price' => 70, 'cat' => 1, 'brand' => 1],
            ['name' => 'Potato Chips 50g', 'cost' => 35, 'price' => 60, 'cat' => 2, 'brand' => 3],
            ['name' => 'Chocolate Bar', 'cost' => 55, 'price' => 90, 'cat' => 2, 'brand' => 3],
            ['name' => 'Dishwashing Liquid 500ml', 'cost' => 160, 'price' => 240, 'cat' => 3, 'brand' => 2],
            ['name' => 'Laundry Detergent 1kg', 'cost' => 320, 'price' => 450, 'cat' => 3, 'brand' => 2],
            ['name' => 'Toilet Soap 3-Pack', 'cost' => 95, 'price' => 140, 'cat' => 4, 'brand' => 2],
            ['name' => 'Shampoo 200ml', 'cost' => 210, 'price' => 320, 'cat' => 4, 'brand' => 2],
            ['name' => 'Instant Noodles Pack', 'cost' => 40, 'price' => 65, 'cat' => 0, 'brand' => 1],
        ];

        $variants = [];
        $seq = 1;

        foreach ($singles as $row) {
            $code = 'D'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $product = Product::query()->create([
                'name' => $row['name'],
                'type' => 'single',
                'short_code' => $code,
                'barcode' => '890100'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                'brand_id' => $brands[$row['brand']]->id,
                'category_id' => $categories[$row['cat']]->id,
                'tax_id' => $taxId,
                'purchase_unit_id' => $unitId,
                'sale_unit_id' => $unitId,
                'conversion_rate' => 1,
                'sale_price' => $row['price'],
                'cost_per_unit' => $row['cost'],
                'min_qty_alert' => 10,
                'track_stock' => true,
                'is_active' => true,
            ]);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'name' => null,
                'short_code' => $code,
                'barcode' => $product->barcode,
                'purchase_unit_id' => $unitId,
                'sale_unit_id' => $unitId,
                'conversion_rate' => 1,
                'sale_price' => $row['price'],
                'cost_per_unit' => $row['cost'],
                'is_active' => true,
                'track_serial' => false,
                'sort_order' => $seq,
            ]);

            $this->assignLocations($product, $variant, $branches, $placements, $seq - 1);
            $variants[] = $variant;
            $seq++;
        }

        // Variant products (Size / Pack).
        $variantCatalog = [
            [
                'name' => 'Cola Soft Drink',
                'cat' => 1,
                'brand' => 3,
                'variation' => $variations['size'],
                'options' => [
                    ['label' => 'Small', 'cost' => 45, 'price' => 70],
                    ['label' => 'Medium', 'cost' => 80, 'price' => 120],
                    ['label' => 'Large', 'cost' => 110, 'price' => 160],
                ],
            ],
            [
                'name' => 'Bottled Juice',
                'cat' => 1,
                'brand' => 1,
                'variation' => $variations['size'],
                'options' => [
                    ['label' => 'Small', 'cost' => 60, 'price' => 95],
                    ['label' => 'Medium', 'cost' => 95, 'price' => 140],
                    ['label' => 'Large', 'cost' => 140, 'price' => 200],
                ],
            ],
            [
                'name' => 'Biscuits Pack',
                'cat' => 2,
                'brand' => 3,
                'variation' => $variations['pack'],
                'options' => [
                    ['label' => 'Single', 'cost' => 40, 'price' => 65],
                    ['label' => 'Twin Pack', 'cost' => 75, 'price' => 120],
                    ['label' => 'Family Pack', 'cost' => 180, 'price' => 250],
                ],
            ],
            [
                'name' => 'Tissue Box',
                'cat' => 3,
                'brand' => 2,
                'variation' => $variations['pack'],
                'options' => [
                    ['label' => 'Single', 'cost' => 90, 'price' => 140],
                    ['label' => 'Twin Pack', 'cost' => 160, 'price' => 250],
                    ['label' => 'Family Pack', 'cost' => 280, 'price' => 420],
                ],
            ],
        ];

        foreach ($variantCatalog as $row) {
            /** @var Variation $variation */
            $variation = $row['variation'];
            $optionsByName = $variation->options->keyBy('name');
            $baseCode = 'D'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            $product = Product::query()->create([
                'name' => $row['name'],
                'type' => 'variant',
                'short_code' => $baseCode,
                'barcode' => null,
                'brand_id' => $brands[$row['brand']]->id,
                'category_id' => $categories[$row['cat']]->id,
                'variation_id' => $variation->id,
                'tax_id' => $taxId,
                'purchase_unit_id' => $unitId,
                'sale_unit_id' => $unitId,
                'conversion_rate' => 1,
                'sale_price' => $row['options'][0]['price'],
                'cost_per_unit' => $row['options'][0]['cost'],
                'min_qty_alert' => 10,
                'track_stock' => true,
                'is_active' => true,
            ]);

            foreach ($row['options'] as $optIndex => $opt) {
                $option = $optionsByName->get($opt['label']);
                if (! $option) {
                    continue;
                }

                $code = $baseCode.'-'.$option->code;
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'variation_option_id' => $option->id,
                    'name' => $option->name,
                    'short_code' => $code,
                    'barcode' => '890200'.str_pad((string) (($seq * 10) + $optIndex), 6, '0', STR_PAD_LEFT),
                    'purchase_unit_id' => $unitId,
                    'sale_unit_id' => $unitId,
                    'conversion_rate' => 1,
                    'sale_price' => $opt['price'],
                    'cost_per_unit' => $opt['cost'],
                    'is_active' => true,
                    'track_serial' => false,
                    'sort_order' => $optIndex + 1,
                ]);

                $this->assignLocations(
                    $product,
                    $variant,
                    $branches,
                    $placements,
                    ($seq - 1) + $optIndex,
                );
                $variants[] = $variant;
            }

            $seq++;
        }

        return $variants;
    }

    /**
     * @param  list<Branch>  $branches
     * @param  list<array{section_id:int, rack_id:int, label:string}>  $placements
     */
    protected function assignLocations(
        Product $product,
        ProductVariant $variant,
        array $branches,
        array $placements,
        int $index,
    ): void {
        if ($placements === []) {
            return;
        }

        foreach ($branches as $branchOffset => $branch) {
            $placement = $placements[($index + $branchOffset) % count($placements)];
            ProductLocation::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'variant_id' => $variant->id,
                ],
                [
                    'product_id' => $product->id,
                    'section_id' => $placement['section_id'],
                    'rack_id' => $placement['rack_id'],
                ],
            );
        }
    }

    /**
     * @return list<Customer>
     */
    protected function seedCustomers(): array
    {
        $rows = [
            ['name' => 'Ali Khan', 'code' => 'C-ALI', 'phone' => '03001234001'],
            ['name' => 'Sara Ahmed', 'code' => 'C-SARA', 'phone' => '03001234002'],
            ['name' => 'Bilal Hussain', 'code' => 'C-BILAL', 'phone' => '03001234003'],
            ['name' => 'Fatima Noor', 'code' => 'C-FATIMA', 'phone' => '03001234004'],
            ['name' => 'Walk-in Retail', 'code' => 'C-WALK', 'phone' => null],
            ['name' => 'Office Supplies Co', 'code' => 'C-OFFICE', 'phone' => '03001234005'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = Customer::query()->create([
                ...$row,
                'email' => null,
                'address' => null,
                'balance' => 0,
                'is_active' => true,
            ]);
        }

        return $out;
    }

    /**
     * @return list<Supplier>
     */
    protected function seedSuppliers(): array
    {
        $rows = [
            ['name' => 'Metro Wholesale', 'code' => 'S-METRO', 'phone' => '021-1110001'],
            ['name' => 'City Distributors', 'code' => 'S-CITY', 'phone' => '021-1110002'],
            ['name' => 'Fresh Supply Hub', 'code' => 'S-FRESH', 'phone' => '021-1110003'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = Supplier::query()->create([
                ...$row,
                'contact_person' => 'Sales Desk',
                'email' => strtolower($row['code']).'@demo.test',
                'address' => 'Wholesale Market',
                'notes' => 'Demo supplier',
                'balance' => 0,
                'is_active' => true,
            ]);
        }

        return $out;
    }

    /**
     * @param  list<ProductVariant>  $variants
     */
    protected function seedPurchase(
        Branch $branch,
        Supplier $supplier,
        array $variants,
        Carbon $date,
        int $multiplier = 2,
        int $hour = 8,
        ?int $lineCount = null,
    ): void {
        if ($variants === []) {
            return;
        }

        $lineCount ??= min(8, count($variants));
        $lineCount = max(1, min($lineCount, count($variants)));
        $picks = collect($variants)->shuffle()->take($lineCount);
        $items = [];
        foreach ($picks as $variant) {
            $qty = random_int(10, 40) * max(1, $multiplier);
            $item = [
                'variant_id' => $variant->id,
                'unit_id' => $variant->purchase_unit_id,
                'quantity' => $qty,
                'unit_price' => (float) $variant->cost_per_unit,
            ];

            // Roughly half the lines get an expiry (near-term to longer shelf life).
            if (random_int(1, 100) <= 55) {
                $item['expiry_date'] = $date->copy()
                    ->addDays(random_int(20, 365))
                    ->toDateString();
            }

            $items[] = $item;
        }

        Carbon::setTestNow($date->copy()->setTime($hour, random_int(0, 45)));
        BranchContext::set($branch->id);

        // Mix payment styles: unpaid credit, partial, or fully paid.
        $style = random_int(1, 100);
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        $subtotal = round($subtotal, 2);

        $cash = MoneySource::query()->where('code', 'cash')->first();
        $paidAmount = 0.0;
        $moneySourceId = null;
        if ($cash && $style > 55) {
            $paidAmount = $style > 80
                ? $subtotal
                : round($subtotal * (random_int(40, 75) / 100), 2);
            $moneySourceId = $cash->id;
        }

        $payload = [
            'supplier_id' => $supplier->id,
            'purchase_date' => $date->toDateString(),
            'tax_total' => 0,
            'discount_total' => 0,
            'paid_amount' => $paidAmount,
            'notes' => "Demo purchase · {$supplier->name} · {$branch->code}",
            'items' => $items,
        ];
        if ($moneySourceId && $paidAmount > 0) {
            $payload['money_source_id'] = $moneySourceId;
        }

        try {
            $this->purchases->create($payload);
        } catch (Throwable) {
            // Fallback: unpaid purchase if till can't cover payment.
            $payload['paid_amount'] = 0;
            unset($payload['money_source_id']);
            $this->purchases->create($payload);
        }
    }

    protected function openDemoShift(
        Branch $branch,
        Carbon $day,
        User $opener,
        MoneySource $cash,
        MoneySource $card,
    ): Shift {
        Carbon::setTestNow($day->copy()->setTime(8, random_int(0, 30)));
        Auth::login($opener);

        $openingCash = random_int(2000, 8000);
        $openingCard = 0;

        $shift = Shift::query()->create([
            'branch_id' => $branch->id,
            'shift_date' => $day->toDateString(),
            'opened_by' => $opener->id,
            'opened_at' => now(),
            'opening_cash' => $openingCash,
            'notes' => 'Demo shift',
        ]);

        ShiftMoneySource::query()->create([
            'shift_id' => $shift->id,
            'money_source_id' => $cash->id,
            'opening_balance' => $openingCash,
        ]);
        ShiftMoneySource::query()->create([
            'shift_id' => $shift->id,
            'money_source_id' => $card->id,
            'opening_balance' => $openingCard,
        ]);

        return $shift;
    }

    protected function closeDemoShift(
        Shift $shift,
        User $closer,
        Carbon $day,
        MoneySource $cash,
    ): void {
        Carbon::setTestNow($day->copy()->setTime(21, random_int(0, 45)));
        Auth::login($closer);

        $shift->load(['sales.payments.moneySource', 'moneySources.moneySource']);

        $cashSales = $shift->sales
            ->flatMap->payments
            ->filter(fn ($p) => $p->moneySource?->isCash() || (int) $p->money_source_id === $cash->id)
            ->sum('amount');

        $cardSales = $shift->sales
            ->flatMap->payments
            ->filter(fn ($p) => ! ($p->moneySource?->isCash() || (int) $p->money_source_id === $cash->id))
            ->sum('amount');

        $expectedCash = round((float) $shift->opening_cash + (float) $cashSales, 2);
        // Slight variance on some days for realistic cash difference.
        $closingCash = random_int(1, 100) <= 20
            ? round($expectedCash + random_int(-150, 150), 2)
            : $expectedCash;
        $closingCash = max(0, $closingCash);

        $shift->update([
            'closed_by' => $closer->id,
            'closed_at' => now(),
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'notes' => trim(($shift->notes ? $shift->notes."\n" : '').'Demo shift closed'),
        ]);

        foreach ($shift->moneySources as $row) {
            $isCash = $row->moneySource?->isCash() || (int) $row->money_source_id === $cash->id;
            $opening = (float) $row->opening_balance;
            $expected = $isCash
                ? round($opening + (float) $cashSales, 2)
                : round($opening + (float) $cardSales, 2);
            $closing = $isCash ? $closingCash : $expected;

            $row->update([
                'closing_balance' => $closing,
                'expected_balance' => $expected,
                'difference' => round($closing - $expected, 2),
            ]);
        }
    }

    /**
     * @param  list<ProductVariant>  $variants
     * @param  list<Customer>  $customers
     */
    protected function seedSale(
        Branch $branch,
        array $variants,
        array $customers,
        MoneySource $cash,
        MoneySource $card,
        User $cashier,
        Carbon $at,
        ?Shift $shift = null,
    ): void {
        $lineCount = random_int(1, 4);
        $picks = collect($variants)->random(min($lineCount, count($variants)));
        $items = [];
        foreach ($picks as $variant) {
            $stock = $this->inventory->getOrCreateStock($branch->id, $variant);
            $available = (float) $stock->quantity;
            if ($available < 1) {
                // Top up a little so sales can continue.
                $this->inventory->receive(
                    branchId: $branch->id,
                    variant: $variant,
                    qtySaleUnits: 50,
                    lineCostTotal: 50 * (float) $variant->cost_per_unit,
                    notes: 'Demo auto-restock',
                    type: 'opening',
                );
                $available = 50;
            }

            $qty = min($available, (float) random_int(1, 3));
            if ($qty < 1) {
                continue;
            }

            $items[] = [
                'variant_id' => $variant->id,
                'unit_id' => $variant->sale_unit_id,
                'quantity' => $qty,
                'unit_price' => (float) $variant->sale_price,
                'discount' => 0,
            ];
        }

        if ($items === []) {
            return;
        }

        $useCustomer = random_int(1, 100) <= 35;
        $customer = $useCustomer ? $customers[array_rand($customers)] : null;
        $payWithCard = random_int(1, 100) <= 40;
        // Only allow unpaid/partial when a customer is attached.
        $partialCredit = $customer !== null && random_int(1, 100) <= 12;

        $estimate = 0.0;
        foreach ($items as $item) {
            $estimate += $item['quantity'] * $item['unit_price'];
        }
        $estimate = round($estimate, 2);

        $paid = $partialCredit
            ? round(max(0.01, $estimate * (random_int(40, 70) / 100)), 2)
            : $estimate;

        if ($paid + 0.01 < $estimate && ! $customer) {
            $paid = $estimate;
        }

        $payments = [[
            'money_source_id' => $payWithCard ? $card->id : $cash->id,
            'amount' => $paid,
        ]];

        Auth::login($cashier);
        Carbon::setTestNow($at);

        try {
            $sale = $this->sales->checkout([
                'branch_id' => $branch->id,
                'shift_id' => $shift?->id,
                'customer_id' => $customer?->id,
                'discount_total' => 0,
                'notes' => 'Demo sale',
                'items' => $items,
                'payments' => $payments,
                'allow_credit' => true,
            ]);
        } catch (Throwable) {
            // Skip rare edge cases rather than aborting the whole seed.
            return;
        }

        $sale->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }
}
