import type { Activity, ActivityType } from '@/types/api'

function metaString(activity: Activity, key: string): string | null {
  const value = activity.metadata?.[key]

  return typeof value === 'string' ? value : null
}

const STATUS_WORDS: Record<string, string> = {
  todo: 'to do',
  in_progress: 'in progress',
  blocked: 'blocked',
  done: 'done',
}

const word = (value: string | null) => (value ? (STATUS_WORDS[value] ?? value) : '—')

/**
 * One line of history, phrased as something a person did.
 *
 * The API reports the subject as a type and an id, so the sentence is built
 * from the metadata each event carries rather than by fetching the subject.
 */
export function describeActivity(activity: Activity): string {
  const describe: Record<ActivityType, () => string> = {
    'task.created': () => `created ${metaString(activity, 'title') ?? 'a task'}`,
    'task.status_changed': () =>
      `moved a task from ${word(metaString(activity, 'old_status'))} to ${word(
        metaString(activity, 'new_status'),
      )}`,
    'task.priority_changed': () =>
      `set task priority to ${word(metaString(activity, 'new_priority'))}`,
    'task.assigned': () =>
      metaString(activity, 'new_assignee_id') ? 'assigned a task' : 'unassigned a task',
    'task.updated': () => 'edited a task',
    'task.deleted': () => `deleted ${metaString(activity, 'title') ?? 'a task'}`,
    'project.created': () => `created project ${metaString(activity, 'name') ?? ''}`.trim(),
    'project.archived': () => 'archived a project',
    'member.invited': () => `invited ${metaString(activity, 'email') ?? 'someone'}`,
    'invitation.accepted': () => 'joined the workspace',
    'member.removed': () => 'removed a member',
    'member.role_changed': () =>
      `changed a member's role to ${metaString(activity, 'new_role') === 'admin' ? 'admin' : 'member'}`,
  }

  return describe[activity.type]?.() ?? activity.type
}
