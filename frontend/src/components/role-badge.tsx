import { cn } from '@/lib/utils'
import type { WorkspaceRole } from '@/types/api'

/**
 * Roles are chrome, not work state, so they stay neutral: only the admin role
 * gets any weight, and it earns that from a border rather than a fill.
 */
export function RoleBadge({ role, className }: { role: WorkspaceRole; className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium',
        role === 'admin' ? 'border-border text-foreground border' : 'text-muted-foreground',
        className,
      )}
    >
      {role === 'admin' ? 'Admin' : 'Member'}
    </span>
  )
}
