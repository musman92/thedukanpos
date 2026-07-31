<?php

namespace App\Services;

use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectionService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{sections: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $sections = Section::query()
            ->with(['racks' => fn ($query) => $query->orderBy('name')->orderBy('id')])
            ->withCount('racks')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhereHas('racks', function ($rack) use ($q) {
                            $rack->where('name', 'like', "%{$q}%")
                                ->orWhere('code', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'sections' => $sections,
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
        $allowed = ['id', 'name', 'code'];
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
     * @param  array{name:string, code?:string|null, is_active?:bool, racks?:list<array{id?:int|null, name:string, code?:string|null, is_active?:bool}>}  $data
     */
    public function create(array $data): Section
    {
        $name = trim((string) $data['name']);
        $code = Section::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        return DB::transaction(function () use ($data, $name, $code) {
            $section = Section::query()->create([
                'name' => $name,
                'code' => $code,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->syncRacks($section, $data['racks'] ?? []);

            return $section->load(['racks' => fn ($q) => $q->orderBy('name')->orderBy('id')])
                ->loadCount('racks');
        });
    }

    /**
     * @param  array{name:string, code?:string|null, is_active?:bool, racks?:list<array{id?:int|null, name:string, code?:string|null, is_active?:bool}>}  $data
     */
    public function update(Section $section, array $data): Section
    {
        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($section->code ?: Section::resolveCode(null));

        $this->assertNameAvailable($name, $section->id);
        $this->assertCodeAvailable($code, $section->id);

        return DB::transaction(function () use ($section, $data, $name, $code) {
            $section->update([
                'name' => $name,
                'code' => $code,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $section->is_active,
            ]);

            if (array_key_exists('racks', $data)) {
                $this->syncRacks($section, $data['racks'] ?? []);
            }

            return $section->refresh()
                ->load(['racks' => fn ($q) => $q->orderBy('name')->orderBy('id')])
                ->loadCount('racks');
        });
    }

    public function delete(Section $section): void
    {
        $locationCount = ProductLocation::query()
            ->where('section_id', $section->id)
            ->count();

        if ($locationCount > 0) {
            throw ValidationException::withMessages([
                'section' => "Cannot delete this section because it is used by {$locationCount} product location(s).",
            ]);
        }

        $rackIds = $section->racks()->pluck('id');
        if ($rackIds->isNotEmpty()) {
            $rackLocations = ProductLocation::query()
                ->whereIn('rack_id', $rackIds)
                ->count();

            if ($rackLocations > 0) {
                throw ValidationException::withMessages([
                    'section' => "Cannot delete this section because its racks are used by {$rackLocations} product location(s).",
                ]);
            }
        }

        $section->delete();
    }

    /**
     * @param  list<array{id?:int|null, name?:string, code?:string|null, is_active?:bool}>  $racks
     */
    protected function syncRacks(Section $section, array $racks): void
    {
        $keepIds = [];
        $seenNames = [];
        $seenCodes = [];

        foreach (array_values($racks) as $row) {
            $rackName = trim((string) ($row['name'] ?? ''));
            if ($rackName === '') {
                continue;
            }

            $nameKey = mb_strtolower($rackName);
            if (isset($seenNames[$nameKey])) {
                throw ValidationException::withMessages([
                    'racks' => "Duplicate rack name \"{$rackName}\".",
                ]);
            }
            $seenNames[$nameKey] = true;

            $codeInput = trim((string) ($row['code'] ?? ''));
            $code = $codeInput !== '' ? strtoupper($codeInput) : null;
            if ($code !== null) {
                $codeKey = strtolower($code);
                if (isset($seenCodes[$codeKey])) {
                    throw ValidationException::withMessages([
                        'racks' => "Duplicate rack code \"{$code}\".",
                    ]);
                }
                $seenCodes[$codeKey] = true;
            }

            $rackId = isset($row['id']) && $row['id'] !== '' && $row['id'] !== null
                ? (int) $row['id']
                : null;

            $payload = [
                'name' => $rackName,
                'code' => $code,
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
            ];

            if ($rackId) {
                $rack = Rack::query()
                    ->where('section_id', $section->id)
                    ->where('id', $rackId)
                    ->first();

                if (! $rack) {
                    throw ValidationException::withMessages([
                        'racks' => 'One of the racks could not be found for this section.',
                    ]);
                }

                $rack->update($payload);
                $keepIds[] = $rack->id;
            } else {
                $created = $section->racks()->create($payload);
                $keepIds[] = $created->id;
            }
        }

        $toDelete = $section->racks()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->pluck('id');

        if ($toDelete->isNotEmpty()) {
            $inUse = ProductLocation::query()->whereIn('rack_id', $toDelete)->count();
            if ($inUse > 0) {
                throw ValidationException::withMessages([
                    'racks' => "Cannot remove rack(s) used by {$inUse} product location(s).",
                ]);
            }

            Rack::query()->whereIn('id', $toDelete)->delete();
        }
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Section::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This section name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Section::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This section code is already taken.',
            ]);
        }
    }
}
