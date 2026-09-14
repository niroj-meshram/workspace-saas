<?php

namespace App\Enums;

/**
 * Projects are archived rather than deleted; archived is not the same as
 * deleted (PROJECT_SPEC.md §11).
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
