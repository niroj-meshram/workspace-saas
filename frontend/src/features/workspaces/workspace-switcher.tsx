import { useState } from 'react'
import { Check, ChevronsUpDown, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { CreateWorkspaceDialog } from '@/features/workspaces/create-workspace-dialog'
import { cn } from '@/lib/utils'

/** The initial mark: a workspace's identity at a glance in the sidebar. */
function WorkspaceMark({ name, className }: { name: string; className?: string }) {
  return (
    <span
      className={cn(
        'bg-primary text-primary-foreground grid size-6 shrink-0 place-items-center rounded-md text-[0.6875rem] font-semibold',
        className,
      )}
      aria-hidden
    >
      {name.trim().charAt(0).toUpperCase()}
    </span>
  )
}

export function WorkspaceSwitcher() {
  const { workspaces, workspace, select, isLoading } = useWorkspace()
  const [creating, setCreating] = useState(false)

  if (isLoading) {
    return <Skeleton className="h-9 w-full" />
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            className="h-9 w-full justify-between px-2"
            aria-label="Switch workspace"
          >
            <span className="flex min-w-0 items-center gap-2">
              {workspace ? <WorkspaceMark name={workspace.name} /> : null}
              <span className="truncate text-sm font-medium">
                {workspace?.name ?? 'No workspace'}
              </span>
            </span>
            <ChevronsUpDown className="text-muted-foreground size-3.5 shrink-0" />
          </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" className="w-60">
          {workspaces.map((candidate) => (
            <DropdownMenuItem
              key={candidate.id}
              onSelect={() => select(candidate.id)}
              className="gap-2"
            >
              <WorkspaceMark name={candidate.name} />
              <span className="min-w-0 flex-1 truncate">{candidate.name}</span>
              {candidate.id === workspace?.id ? <Check className="size-4" /> : null}
            </DropdownMenuItem>
          ))}

          {workspaces.length > 0 ? <DropdownMenuSeparator /> : null}

          <DropdownMenuItem onSelect={() => setCreating(true)} className="gap-2">
            <Plus className="size-4" />
            New workspace
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <CreateWorkspaceDialog open={creating} onOpenChange={setCreating} />
    </>
  )
}
