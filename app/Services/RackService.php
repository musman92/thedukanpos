<?php

namespace App\Services;

use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RackService
{
    /**
     * @param  array{q?:string|null, section_id?:int|string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{racks: LengthAwarePaginator, filters: array{q: string, section_id: int|null, per_page: int, company_page_limit: int, sort: string, direction: string}}
     */
    public function paginate(array $filters = []): array
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $sectionId = $filters['section_id'] ?? null;
        $sectionId = $sectionId !== null && $sectionId !== '' ? (int) $sectionId : null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $racks = Rack::query()
            ->with(['section:id,name,code'])
            ->withCount('locations')
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhereHas('section', function ($section) use ($q) {
                            $section->where('name', 'like', "%{$q}%")
                                ->orWhere('code', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'racks' => $racks,
            'filters' => [
                'q' => $q,
                'section_id' => $sectionId,
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
     * @param  array{section_id:int, name:string, code?:string|null, is_active?:bool}  $data
     */
    public function create(array $data): Rack
    {
        $sectionId = (int) $data['section_id'];
        $this->assertSectionExists($sectionId);

        $name = trim((string) $data['name']);
        $code = Rack::resolveCode($data['code'] ?? null, $sectionId);
        $this->assertNameAvailable($sectionId, $name);
        $this->assertCodeAvailable($sectionId, $code);

        return Rack::query()->create([
            'section_id' => $sectionId,
            'name' => $name,
            'code' => $code,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{section_id:int, name:string, code?:string|null, is_active?:bool}  $data
     */
    public function update(Rack $rack, array $data): Rack
    {
        $sectionId = array_key_exists('section_id', $data)
            ? (int) $data['section_id']
            : (int) $rack->section_id;
        $this->assertSectionExists($sectionId);

        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($rack->code ?: Rack::resolveCode(null, $sectionId));

        $this->assertNameAvailable($sectionId, $name, $rack->id);
        $this->assertCodeAvailable($sectionId, $code, $rack->id);

        $rack->update([
            'section_id' => $sectionId,
            'name' => $name,
            'code' => $code,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rack->is_active,
        ]);

        return $rack->refresh();
    }

    public function delete(Rack $rack): void
    {
        $count = ProductLocation::query()->where('rack_id', $rack->id)->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'rack' => "Cannot delete this rack because it is used by {$count} product location(s).",
            ]);
        }

        $rack->delete();
    }

    protected function assertSectionExists(int $sectionId): void
    {
        if (! Section::query()->whereKey($sectionId)->exists()) {
            throw ValidationException::withMessages([
                'section_id' => 'The selected section is invalid.',
            ]);
        }
    }

    protected function assertNameAvailable(int $sectionId, string $name, ?int $ignoreId = null): void
    {
        $exists = Rack::query()
            ->where('section_id', $sectionId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This rack name is already taken in this section.',
            ]);
        }
    }

    protected function assertCodeAvailable(int $sectionId, string $code, ?int $ignoreId = null): void
    {
        $exists = Rack::query()
            ->where('section_id', $sectionId)
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This rack code is already taken in this section.',
            ]);
        }
    }
}
