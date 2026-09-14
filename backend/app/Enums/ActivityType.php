<?php

namespace App\Enums;

/**
 * Domain events recorded in the activity log (PROJECT_SPEC.md §9).
 *
 * Adding a new recorded event means adding a case here first; the column is
 * cast to this enum, so unknown values are rejected when reading.
 *
 * task.updated, task.deleted and invitation.accepted are not in §9's list,
 * which is explicitly a set of examples; they are required so that edits to a
 * task's content, its removal, and the acceptance of an invitation are
 * auditable too.
 */
enum ActivityType: string
{
    case TaskCreated = 'task.created';
    case TaskStatusChanged = 'task.status_changed';
    case TaskAssigned = 'task.assigned';
    case TaskPriorityChanged = 'task.priority_changed';
    case TaskUpdated = 'task.updated';
    case TaskDeleted = 'task.deleted';
    case ProjectCreated = 'project.created';
    case ProjectArchived = 'project.archived';
    case MemberInvited = 'member.invited';
    case InvitationAccepted = 'invitation.accepted';
    case MemberRemoved = 'member.removed';
    case MemberRoleChanged = 'member.role_changed';
}
