import { useState } from 'react'
import { Link } from 'react-router'
import { Archive, FolderKanban, MoreHorizontal, Pencil, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Panel } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { EmptyState, ErrorState, RowsSkeleton } from '@/components/states'
import { ProjectStatusChip } from '@/components/status-chip'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { ProjectDialog } from '@/features/projects/project-dialog'
import { useArchiveProject, useProjects } from '@/features/projects/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { toApiError } from '@/lib/api-error'
import type { Project } from '@/types/api'

export function ProjectsPage() {
  const { workspace, isAdmin } = useWorkspace()
  const workspaceId = workspace!.id

  const [page, setPage] = useState(1)
  const { data, isPending, isError, refetch } = useProjects(workspaceId, page)
  const archiveProject = useArchiveProject(workspaceId)

  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Project | null>(null)
  const [archiving, setArchiving] = useState<Project | null>(null)

  const confirmArchive = async () => {
    if (!archiving) return

    try {
      await archiveProject.mutateAsync(archiving.id)
      toast.success(`${archiving.name} archived`)
      setArchiving(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const projects = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <PageHeader
        title="Projects"
        description="Group related work. Archived projects stay readable but stop taking new tasks."
        action={
          isAdmin ? (
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" />
              New project
            </Button>
          ) : null
        }
      />

      <Panel>
        {isPending ? (
          <RowsSkeleton />
        ) : isError ? (
          <ErrorState description="Projects didn't load." onRetry={refetch} />
        ) : projects.length === 0 ? (
          <EmptyState
            icon={FolderKanban}
            title="No projects yet"
            description={
              isAdmin
                ? 'Create a project to start grouping tasks.'
                : 'An admin of this workspace can create the first one.'
            }
            action={
              isAdmin ? <Button onClick={() => setCreating(true)}>New project</Button> : undefined
            }
          />
        ) : (
          <ul className="divide-border divide-y">
            {projects.map((project) => (
              <li
                key={project.id}
                className="hover:bg-accent/40 flex items-center gap-3 px-5 py-3.5 transition-colors"
              >
                <div className="min-w-0 flex-1">
                  <Link
                    to={`/projects/${project.id}`}
                    className="truncate text-sm font-medium underline-offset-4 hover:underline"
                  >
                    {project.name}
                  </Link>
                  {project.description ? (
                    <p className="text-muted-foreground mt-0.5 truncate text-xs">
                      {project.description}
                    </p>
                  ) : null}
                </div>

                <ProjectStatusChip status={project.status} />

                {isAdmin ? (
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${project.name}`}
                      >
                        <MoreHorizontal className="size-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onSelect={() => setEditing(project)} className="gap-2">
                        <Pencil className="size-4" />
                        Edit
                      </DropdownMenuItem>
                      {project.status === 'active' ? (
                        <DropdownMenuItem onSelect={() => setArchiving(project)} className="gap-2">
                          <Archive className="size-4" />
                          Archive
                        </DropdownMenuItem>
                      ) : null}
                    </DropdownMenuContent>
                  </DropdownMenu>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </Panel>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between">
          <p className="text-muted-foreground text-sm">
            Page {meta.current_page} of {meta.last_page}
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

      <ProjectDialog workspaceId={workspaceId} open={creating} onOpenChange={setCreating} />

      {editing ? (
        <ProjectDialog
          workspaceId={workspaceId}
          project={editing}
          open
          onOpenChange={(open) => !open && setEditing(null)}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(archiving)}
        onOpenChange={(open) => !open && setArchiving(null)}
        title={`Archive ${archiving?.name ?? 'this project'}?`}
        description="Its tasks and history stay readable. The project stops accepting new tasks until an admin makes it active again."
        confirmLabel="Archive project"
        onConfirm={confirmArchive}
        isPending={archiveProject.isPending}
      />
    </div>
  )
}
