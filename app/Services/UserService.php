<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\TenantDefaultRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(protected HrService $hr) {}

    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{users: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
     */
    public function paginate(array $filters = []): array
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $users = User::query()
            ->with(['roles:id,name', 'branch:id,name', 'employeeProfile.branch:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhereHas('employeeProfile', function ($profile) use ($q) {
                            $profile->where('employee_number', 'like', "%{$q}%")
                                ->orWhere('designation', 'like', "%{$q}%")
                                ->orWhere('department', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return [
            'users' => $users,
            'filters' => [
                'q' => $q,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'name', 'username'];
        $sort = strtolower(trim((string) ($sort ?? 'id')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $sort === 'id' ? 'desc' : 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $canLogin = (bool) ($data['can_login'] ?? true);
            $isEmployee = (bool) ($data['is_employee'] ?? false);

            $username = $canLogin
                ? strtolower(trim((string) $data['username']))
                : $this->generateUsername($data['name'] ?? 'user');

            $this->assertUsernameAvailable($username);

            if ($canLogin) {
                $password = (string) ($data['password'] ?? '');
                if (strlen($password) < 6) {
                    throw ValidationException::withMessages([
                        'password' => 'Password must be at least 6 characters.',
                    ]);
                }
                $role = trim((string) ($data['role'] ?? ''));
                $this->assertRoleExists($role);
            } else {
                $password = Str::password(16);
                $role = null;
            }

            $user = User::query()->create([
                'name' => trim((string) $data['name']),
                'username' => $username,
                'email' => $this->nullableString($data['email'] ?? null),
                'phone' => $this->nullableString($data['phone'] ?? null),
                'password' => Hash::make($password),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'can_login' => $canLogin,
                'branch_id' => $data['branch_id'] ?? null,
            ]);

            if ($role) {
                $user->syncRoles([$role]);
            }

            if (! empty($data['branch_id'])) {
                $user->branches()->syncWithoutDetaching([
                    (int) $data['branch_id'] => ['is_primary' => true],
                ]);
            }

            if ($isEmployee) {
                $this->syncEmployeeProfile($user, $data);
            }

            return $user->fresh(['roles', 'branch', 'employeeProfile.branch']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $canLogin = array_key_exists('can_login', $data)
                ? (bool) $data['can_login']
                : (bool) $user->can_login;
            $isEmployee = array_key_exists('is_employee', $data)
                ? (bool) $data['is_employee']
                : $user->employeeProfile()->exists();

            $payload = [
                'name' => trim((string) $data['name']),
                'email' => $this->nullableString($data['email'] ?? null),
                'phone' => $this->nullableString($data['phone'] ?? null),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $user->is_active,
                'can_login' => $canLogin,
                'branch_id' => $data['branch_id'] ?? null,
            ];

            if ($canLogin) {
                $username = strtolower(trim((string) ($data['username'] ?? $user->username)));
                $this->assertUsernameAvailable($username, $user->id);
                $payload['username'] = $username;

                $password = trim((string) ($data['password'] ?? ''));
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        throw ValidationException::withMessages([
                            'password' => 'Password must be at least 6 characters.',
                        ]);
                    }
                    $payload['password'] = Hash::make($password);
                }

                $role = trim((string) ($data['role'] ?? ''));
                if ($role !== '') {
                    $this->assertRoleExists($role);
                    $user->syncRoles([$role]);
                }
            } else {
                $user->syncRoles([]);
            }

            $user->update($payload);

            if (! empty($data['branch_id'])) {
                $user->branches()->sync([
                    (int) $data['branch_id'] => ['is_primary' => true],
                ]);
            } else {
                $user->branches()->detach();
            }

            if ($isEmployee) {
                $this->syncEmployeeProfile($user, $data);
            } elseif ($user->employeeProfile) {
                $user->employeeProfile->delete();
            }

            return $user->fresh(['roles', 'branch', 'employeeProfile.branch']);
        });
    }

    public function delete(User $user): void
    {
        if (Auth::id() === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole(TenantDefaultRoles::ADMINISTRATOR)) {
            $otherAdmins = User::query()
                ->role(TenantDefaultRoles::ADMINISTRATOR)
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->where('can_login', true)
                ->count();

            if ($otherAdmins === 0) {
                throw ValidationException::withMessages([
                    'user' => 'Cannot delete the last active administrator.',
                ]);
            }
        }

        DB::transaction(function () use ($user) {
            $user->employeeProfile?->delete();
            $user->branches()->detach();
            $user->syncRoles([]);
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $profile = $user->employeeProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'can_login' => (bool) $user->can_login,
            'branch_id' => $user->branch_id,
            'branch' => $user->branch,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'is_employee' => $profile !== null,
            'employee_profile' => $profile ? [
                'id' => $profile->id,
                'employee_number' => $profile->employee_number,
                'designation' => $profile->designation,
                'department' => $profile->department,
                'hire_date' => $profile->hire_date?->format('Y-m-d'),
                'employment_status' => $profile->employment_status,
                'pay_frequency' => $profile->pay_frequency,
                'pay_rate' => $profile->pay_rate,
                'branch_id' => $profile->branch_id,
                'phone' => $profile->phone,
                'address' => $profile->address,
                'notes' => $profile->notes,
                'branch' => $profile->branch,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncEmployeeProfile(User $user, array $data): EmployeeProfile
    {
        $number = trim((string) ($data['employee_number'] ?? ''));
        if ($number === '') {
            $number = $this->nextEmployeeNumber();
        }

        $this->assertEmployeeNumberAvailable($number, $user->id);

        return $this->hr->upsertEmployeeProfile([
            'user_id' => $user->id,
            'branch_id' => $data['employee_branch_id'] ?? $data['branch_id'] ?? null,
            'employee_number' => $number,
            'designation' => $this->nullableString($data['designation'] ?? null),
            'department' => $this->nullableString($data['department'] ?? null),
            'hire_date' => $data['hire_date'] ?? null,
            'employment_status' => $data['employment_status'] ?? 'active',
            'pay_frequency' => $data['pay_frequency'] ?? 'monthly',
            'pay_rate' => $data['pay_rate'] ?? 0,
            'phone' => $this->nullableString($data['employee_phone'] ?? $data['phone'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ]);
    }

    public function nextEmployeeNumber(): string
    {
        $numbers = EmployeeProfile::query()
            ->whereNotNull('employee_number')
            ->pluck('employee_number');

        $max = 0;
        foreach ($numbers as $number) {
            if (preg_match('/^e0*(\d+)$/i', (string) $number, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;

        return 'E'.str_pad((string) $next, max(2, strlen((string) $next)), '0', STR_PAD_LEFT);
    }

    protected function generateUsername(string $name): string
    {
        $base = Str::slug(Str::lower($name), '');
        if ($base === '') {
            $base = 'user';
        }
        $base = Str::limit($base, 40, '');

        $candidate = $base;
        $i = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.$i;
            $i++;
        }

        return $candidate;
    }

    protected function assertUsernameAvailable(string $username, ?int $ignoreId = null): void
    {
        $exists = User::query()
            ->where('username', $username)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'username' => 'This username is already taken.',
            ]);
        }
    }

    protected function assertEmployeeNumberAvailable(string $number, ?int $userId = null): void
    {
        $exists = EmployeeProfile::query()
            ->whereRaw('UPPER(employee_number) = ?', [strtoupper($number)])
            ->when($userId, fn ($q) => $q->where('user_id', '!=', $userId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_number' => 'This employee number is already taken.',
            ]);
        }
    }

    protected function assertRoleExists(string $role): void
    {
        if ($role === '' || ! Role::query()->where('name', $role)->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Please choose a valid role.',
            ]);
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
