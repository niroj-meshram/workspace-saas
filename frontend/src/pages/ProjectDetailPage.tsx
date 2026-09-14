import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { ArchiveRestore, ArrowLeft, ListChecks, Pencil } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Panel, PanelHeader } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { EmptyState, ErrorState, RowsSkeleton } from '@/components/states'
import { ProjectStatusChip } from '@/components/status-chip'
import { TaskRow } from '@/features/tasks/task-row'
import { ProjectDialog } from '@/features/projects/project-dialog'
import { useProject, useUpdateProject } from '@/features/projects/hooks'
import { useTasks } from '@/features/tasks/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { toApiError } from '@/lib/api-error'
import { FullPageSpinner } from '@/components/full-page-spinner'

export function ProjectDetailPage() {
  const { projectId = '' } = useParams()
  const { workspace, isAdmin } = useWorkspace()
  const workspaceId = workspace!.id

  const project = useProject(workspaceId, projectId)
  const tasks = useTasks(workspaceId, { project_id: projectId, per_page: 10 })
  const updateProject = useUpdateProject(workspaceId, projectId)
  const [editing, setEditing] = useState(false)

  const restore = async () => {
    try {
      await updateProject.mutateAsync({ status: 'active' })
      toast.success('Project is active again')
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  if (project.isPending) {
    return <FullPageSpinner label="Loading project" />
  }

  if (project.isError) {
    return (
      <Panel>
        <ErrorState
          title="Project not found"
          description="It may have been removed, or it belongs to another workspace."
          onRetry={project.refetch}
        />
      </Panel>
    )
  }

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to="/projects">
          <ArrowLeft className="size-4" />
          Projects
        </Link>
      </Button>

      <PageHeader
        title={project.data.name}
        description={project.data.description ?? undefined}
        action={
          isAdmin ? (
            <div className="flex gap-2">
              {project.data.status === 'archived' ? (
                <Button variant="outline" onClick={restore} disabled={updateProject.isPending}>
                  <ArchiveRestore className="size-4" />
                  {updateProject.isPending ? 'Restoring…' : 'Make active'}
                </Button>
              ) : null}
              <Button variant="outline" onClick={() => setEditing(true)}>
                <Pencil className="size-4" />
                Edit
              </Button>
            </div>
          ) : null
        }
      />

      <ProjectStatusChip status={project.data.status} />

      <Panel>
        <PanelHeader
          title="Tasks"
          action={
            <Button variant="ghost" size="sm" asChild>
              <Link to={`/tasks?project_id=${project.data.id}`}>View all</Link>
            </Button>
          }
        />

        {tasks.isPending ? (
          <RowsSkeleton rows={4} />
        ) : tasks.isError ? (
          <ErrorState description="Tasks didn't load." onRetry={tasks.refetch} />
        ) : tasks.data && tasks.data.data.length > 0 ? (
          <ul className="divide-border divide-y">
            {tasks.data.data.map((task) => (
              <TaskRow key={task.id} task={task} />
            ))}
          </ul>
        ) : (
          <EmptyState
            icon={ListChecks}
            title="No tasks in this project"
            description={
              project.data.status === 'archived'
                ? 'Archived projects stop accepting new tasks.'
                : 'Add the first one from the tasks screen.'
            }
            action={
              project.data.status === 'active' ? (
                <Button size="sm" asChild>
                  <Link to={`/tasks?project_id=${project.data.id}`}>Go to tasks</Link>
                </Button>
              ) : undefined
            }
          />
        )}
      </Panel>

      <ProjectDialog
        workspaceId={workspaceId}
        project={project.data}
        open={editing}
        onOpenChange={setEditing}
      />
    </div>
  )
}
