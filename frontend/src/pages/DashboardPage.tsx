import { Link } from 'react-router'
import { Activity as ActivityIcon, ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Panel, PanelHeader } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { EmptyState, ErrorState } from '@/components/states'
import { TaskRow } from '@/features/tasks/task-row'
import { ActivityLine } from '@/features/activity/activity-line'
import { useDashboard } from '@/features/dashboard/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import type { DashboardStats } from '@/types/api'

const STAT_ORDER: { key: keyof DashboardStats; label: string }[] = [
  { key: 'projects', label: 'Projects' },
  { key: 'todo', label: 'To do' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'completed', label: 'Completed' },
]

/**
 * Four figures in one panel divided by hairlines, rather than four separate
 * cards. They are one comparison, so they share one surface; tabular numerals
 * keep the digits from shifting as the counts change.
 */
function StatStrip({ stats, isLoading }: { stats?: DashboardStats; isLoading: boolean }) {
  return (
    <Panel
      label="Workspace stats"
      className="grid grid-cols-2 divide-x divide-y sm:grid-cols-4 sm:divide-y-0"
    >
      {STAT_ORDER.map(({ key, label }) => (
        <div key={key} className="px-5 py-4">
          {isLoading ? (
            <Skeleton className="h-8 w-12" />
          ) : (
            <p className="tabular text-[2rem] leading-none font-semibold">{stats?.[key] ?? 0}</p>
          )}
          <p className="text-muted-foreground mt-2 text-[0.8125rem]">{label}</p>
        </div>
      ))}
    </Panel>
  )
}

export function DashboardPage() {
  const { workspace } = useWorkspace()
  const { data, isPending, isError, refetch } = useDashboard(workspace!.id)

  return (
    <div className="space-y-6">
      <PageHeader
        title={workspace!.name}
        description="What's moving in this workspace right now."
      />

      {isError ? (
        <Panel>
          <ErrorState
            description="The dashboard didn't load. The server may be unavailable."
            onRetry={refetch}
          />
        </Panel>
      ) : (
        <>
          <StatStrip stats={data?.stats} isLoading={isPending} />

          <div className="grid items-start gap-6 lg:grid-cols-5">
            <Panel className="lg:col-span-3">
              <PanelHeader
                title="My tasks"
                action={
                  <Button variant="ghost" size="sm" asChild>
                    <Link to="/tasks">View all</Link>
                  </Button>
                }
              />

              {isPending ? (
                <div className="space-y-3 px-5 py-4">
                  {[0, 1, 2].map((row) => (
                    <Skeleton key={row} className="h-9 w-full" />
                  ))}
                </div>
              ) : data && data.my_tasks.length > 0 ? (
                <ul className="divide-border divide-y">
                  {data.my_tasks.map((task) => (
                    <TaskRow key={task.id} task={task} />
                  ))}
                </ul>
              ) : (
                <EmptyState
                  icon={ListChecks}
                  title="Nothing assigned to you"
                  description="Tasks you're the assignee on will collect here."
                  action={
                    <Button size="sm" asChild>
                      <Link to="/tasks">Browse tasks</Link>
                    </Button>
                  }
                />
              )}
            </Panel>

            <Panel className="lg:col-span-2">
              <PanelHeader
                title="Recent activity"
                action={
                  <Button variant="ghost" size="sm" asChild>
                    <Link to="/activity">View all</Link>
                  </Button>
                }
              />

              {isPending ? (
                <div className="space-y-3 px-5 py-4">
                  {[0, 1, 2, 3].map((row) => (
                    <Skeleton key={row} className="h-8 w-full" />
                  ))}
                </div>
              ) : data && data.recent_activity.length > 0 ? (
                <ul className="divide-border divide-y">
                  {data.recent_activity.map((activity) => (
                    <ActivityLine key={activity.id} activity={activity} />
                  ))}
                </ul>
              ) : (
                <EmptyState
                  icon={ActivityIcon}
                  title="No activity yet"
                  description="Once your team starts working, their changes show up here."
                />
              )}
            </Panel>
          </div>
        </>
      )}
    </div>
  )
}
