<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;

/**
 * Gives a model a native PostgreSQL `uuid` primary key populated with a
 * version 4 UUID (PROJECT_SPEC.md §2).
 *
 * Laravel's own `HasUuids` switched to UUID v7, so the version 4 variant is
 * used explicitly. It generates an *ordered* v4 UUID: the RFC version nibble
 * is still 4, but the leading bytes are time-based, which keeps B-tree index
 * locality reasonable on high-insert tables such as `activities`.
 *
 * Centralising this in one trait keeps the choice in a single place instead of
 * spreading framework trait names across every model.
 */
trait HasUuidPrimaryKey
{
    use HasVersion4Uuids;
}
