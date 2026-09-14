import { describe, expect, it } from 'vitest'
import { describeActivity } from '@/features/activity/describe-activity'
import { projectCreatedActivity } from '@/test/fixtures'
import type { Activity, ActivityType } from '@/types/api'

function activity(type: ActivityType, metadata: Record<string, unknown> = {}): Activity {
  return { ...projectCreatedActivity, type, metadata }
}

describe('describeActivity', () => {
  it('names what was created', () => {
    expect(describeActivity(activity('project.created', { name: 'Launch' }))).toBe(
      'created project Launch',
    )
  })

  it('reads a status change in plain words', () => {
    expect(
      describeActivity(
        activity('task.status_changed', { old_status: 'todo', new_status: 'in_progress' }),
      ),
    ).toBe('moved a task from to do to in progress')
  })

  it('distinguishes assigning from unassigning', () => {
    expect(describeActivity(activity('task.assigned', { new_assignee_id: 'user-bob' }))).toBe(
      'assigned a task',
    )
    expect(describeActivity(activity('task.assigned', { new_assignee_id: null }))).toBe(
      'unassigned a task',
    )
  })

  it('falls back gracefully when metadata is missing', () => {
    expect(describeActivity(activity('task.created'))).toBe('created a task')
  })

  it('covers every activity type the API can send', () => {
    const types: ActivityType[] = [
      'task.created',
      'task.status_changed',
      'task.priority_changed',
      'task.assigned',
      'task.updated',
      'task.deleted',
      'project.created',
      'project.archived',
      'member.invited',
      'invitation.accepted',
      'member.removed',
      'member.role_changed',
    ]

    for (const type of types) {
      expect(describeActivity(activity(type))).not.toBe(type)
    }
  })
})
