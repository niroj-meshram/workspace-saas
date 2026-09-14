<?php

namespace App\Enums;

/**
 * Roles are workspace-specific, never global (PROJECT_SPEC.md §3).
 */
enum WorkspaceRole: string
{
    case Admin = 'admin';
    case User = 'user';
}
