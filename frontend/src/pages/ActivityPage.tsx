import { useState } from 'react'
import { Activity as ActivityIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Panel } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { EmptyState, ErrorState, RowsSkeleton } from '@/components/states'
import { ActivityLine } from '@/features/activity/activity-line'
import { useActivities } from '@/features/activity/hooks'
import { useMembers } from '@/features/members/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import type { ActivityType } from '@/types/api'

const ANY = 'any'

const TYPE_LABELS: Record<ActivityType, string> = {
  'task.created': 'Task created',
  'task.status_changed': 'Task status changed',
  'task.priority_changed': 'Task priority changed',
  'task.assigned': 'Task assigned',
  'task.updated': 'Task edited',
  'task.deleted': 'Task deleted',
  'project.created': 'Project created',
  'project.archived': 'Project archived',
  'member.invited': 'Member invited',
  'invitation.accepted': 'Invitation accepted',
  'member.removed': 'Member removed',
  'member.role_changed': 'Role changed',
}

export function ActivityPage() {
  const { workspace } = useWorkspace()
  const workspaceId = workspace!.id

  const [type, setType] = useState<string>(ANY)
  const [userId, setUserId] = useState<string>(ANY)
  const [page, setPage] = useState(1)

  const filters = {
    type: type === ANY ? undefined : (type as ActivityType),
    user_id: userId === ANY ? undefined : userId,
    page,
  }

  const { data, isPending, isError, refetch } = useActivities(workspaceId, filters)
  const members = useMembers(workspaceId)

  const activities = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <PageHeader
        title="Activity"
        description="A permanent record of what changed in this workspace, and who changed it."
      />

      <div className="flex flex-wrap gap-2">
        <Select
          value={type}
          onValueChange={(value) => {
            setType(value)
            setPage(1)
          }}
        >
          <SelectTrigger className="w-auto min-w-44" aria-label="Filter by type">
            <SelectValue placeholder="Any event" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>Any event</SelectItem>
            {Object.entries(TYPE_LABELS).map(([value, label]) => (
              <SelectItem key={value} value={value}>
                {label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={userId}
          onValueChange={(value) => {
            setUserId(value)
            setPage(1)
          }}
        >
          <SelectTrigger className="w-auto min-w-36" aria-label="Filter by person">
            <SelectValue placeholder="Anyone" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>Anyone</SelectItem>
            {(members.data ?? []).map((member) =>
              member.user ? (
                <SelectItem key={member.id} value={member.user.id}>
                  {member.user.name}
                </SelectItem>
              ) : null,
            )}
          </SelectContent>
        </Select>
      </div>

      <Panel>
        {isPending ? (
          <RowsSkeleton />
        ) : isError ? (
          <ErrorState description="The activity feed didn't load." onRetry={refetch} />
        ) : activities.length === 0 ? (
          <EmptyState
            icon={ActivityIcon}
            title={
              type !== ANY || userId !== ANY ? 'Nothing matches those filters' : 'No activity yet'
            }
            description={
              type !== ANY || userId !== ANY
                ? 'Try a different event or person.'
                : 'Changes your team makes will be recorded here.'
            }
          />
        ) : (
          <ul className="divide-border divide-y">
            {activities.map((activity) => (
              <ActivityLine key={activity.id} activity={activity} />
            ))}
          </ul>
        )}
      </Panel>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between">
          <p className="text-muted-foreground text-sm">
            {meta.from}–{meta.to} of {meta.total}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1}
              onClick={() => setPage((current) => current - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  )
}
