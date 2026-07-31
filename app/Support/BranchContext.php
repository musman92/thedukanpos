<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;

class BranchContext
{
    public const HEADER = 'X-Branch-Id';

    public static function id(): ?int
    {
        $request = request();

        if ($request) {
            $header = $request->header(self::HEADER);
            if ($header !== null && $header !== '' && is_numeric($header)) {
                return (int) $header;
            }
        }

        $sessionId = session('current_branch_id');

        if ($sessionId) {
            return (int) $sessionId;
        }

        $user = Auth::user();

        return $user?->branch_id ? (int) $user->branch_id : null;
    }

    public static function branch(): ?Branch
    {
        $id = static::id();

        if (! $id) {
            return null;
        }

        return Branch::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    public static function set(int $branchId): void
    {
        session(['current_branch_id' => $branchId]);
    }

    public static function ensure(): Branch
    {
        $branch = static::branch();

        if ($branch) {
            return $branch;
        }

        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first();

        if (! $branch) {
            abort(422, 'No active branch configured.');
        }

        // Only persist to session when nothing was explicitly selected for this request
        // (e.g. first load / new tab). Header-driven requests should not rewrite session.
        if (! request()?->hasHeader(self::HEADER)) {
            static::set($branch->id);
        }

        return $branch;
    }
}
