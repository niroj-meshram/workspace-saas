<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;

/**
 * The pagination bounds from PROJECT_SPEC.md §13, in one place: 20 items by
 * default, never more than 100 however large a per_page a client asks for.
 */
trait ResolvesPagination
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    protected function perPage(Request $request): int
    {
        return max(1, min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));
    }
}
