<?php

namespace App\Services;

use App\Models\Variation;
use App\Models\VariationOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariationService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{variations: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $variations = Variation::query()
            ->with(['options' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->withCount('options')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhereHas('options', function ($opt) use ($q) {
                            $opt->where('name', 'like', "%{$q}%")
                                ->orWhere('code', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'variations' => $variations,
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
        $allowed = ['id', 'name', 'code', 'sort_order'];
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
     * @param  array{name:string, code?:string|null, sort_order?:int, is_active?:bool, options?:list<array{id?:int|null, name:string, code?:string|null, sort_order?:int, is_active?:bool}>}  $data
     */
    public function create(array $data): Variation
    {
        $name = trim((string) $data['name']);
        $code = Variation::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        return DB::transaction(function () use ($data, $name, $code) {
            $variation = Variation::query()->create([
                'name' => $name,
                'code' => $code,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->syncOptions($variation, $data['options'] ?? []);

            return $variation->load(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->loadCount('options');
        });
    }

    /**
     * @param  array{name:string, code?:string|null, sort_order?:int, is_active?:bool, options?:list<array{id?:int|null, name:string, code?:string|null, sort_order?:int, is_active?:bool}>}  $data
     */
    public function update(Variation $variation, array $data): Variation
    {
        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($variation->code ?: Variation::resolveCode(null));

        $this->assertNameAvailable($name, $variation->id);
        $this->assertCodeAvailable($code, $variation->id);

        return DB::transaction(function () use ($variation, $data, $name, $code) {
            $variation->update([
                'name' => $name,
                'code' => $code,
                'sort_order' => array_key_exists('sort_order', $data)
                    ? (int) $data['sort_order']
                    : $variation->sort_order,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $variation->is_active,
            ]);

            if (array_key_exists('options', $data)) {
                $this->syncOptions($variation, $data['options'] ?? []);
            }

            return $variation->refresh()
                ->load(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->loadCount('options');
        });
    }

    public function delete(Variation $variation): void
    {
        $variation->delete();
    }

    /**
     * @param  list<array{id?:int|null, name?:string, code?:string|null, sort_order?:int, is_active?:bool}>  $options
     */
    protected function syncOptions(Variation $variation, array $options): void
    {
        $keepIds = [];
        $seenNames = [];
        $seenCodes = [];

        foreach (array_values($options) as $index => $row) {
            $optionName = trim((string) ($row['name'] ?? ''));
            if ($optionName === '') {
                continue;
            }

            $nameKey = mb_strtolower($optionName);
            if (isset($seenNames[$nameKey])) {
                throw ValidationException::withMessages([
                    'options' => "Duplicate option name \"{$optionName}\".",
                ]);
            }
            $seenNames[$nameKey] = true;

            $codeInput = trim((string) ($row['code'] ?? ''));
            $code = $codeInput !== '' ? strtoupper($codeInput) : null;
            if ($code !== null) {
                $codeKey = strtolower($code);
                if (isset($seenCodes[$codeKey])) {
                    throw ValidationException::withMessages([
                        'options' => "Duplicate option code \"{$code}\".",
                    ]);
                }
                $seenCodes[$codeKey] = true;
            }

            $optionId = isset($row['id']) && $row['id'] !== '' && $row['id'] !== null
                ? (int) $row['id']
                : null;

            $payload = [
                'name' => $optionName,
                'code' => $code,
                'sort_order' => array_key_exists('sort_order', $row) ? (int) $row['sort_order'] : $index,
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
            ];

            if ($optionId) {
                $option = VariationOption::query()
                    ->where('variation_id', $variation->id)
                    ->where('id', $optionId)
                    ->first();

                if (! $option) {
                    throw ValidationException::withMessages([
                        'options' => 'One of the options could not be found for this variation.',
                    ]);
                }

                $option->update($payload);
                $keepIds[] = $option->id;
            } else {
                $created = $variation->options()->create($payload);
                $keepIds[] = $created->id;
            }
        }

        if ($keepIds === []) {
            $variation->options()->delete();
        } else {
            $variation->options()->whereNotIn('id', $keepIds)->delete();
        }
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Variation::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This variation name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Variation::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This variation code is already taken.',
            ]);
        }
    }
}
