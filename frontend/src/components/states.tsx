import type { ComponentType, ReactNode } from 'react'
import { AlertCircle, type LucideProps } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'

/**
 * Empty, error and loading states (PROJECT_SPEC.md §24).
 *
 * An empty screen is an invitation to act, so it names the next step rather
 * than apologising for having nothing. An error says what failed and offers
 * the way out.
 */

export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
  className,
}: {
  icon?: ComponentType<LucideProps>
  title: string
  description?: string
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('flex flex-col items-center px-6 py-14 text-center', className)}>
      {Icon ? (
        <span className="bg-muted text-muted-foreground mb-4 grid size-10 place-items-center rounded-lg">
          <Icon className="size-5" />
        </span>
      ) : null}
      <p className="text-sm font-semibold">{title}</p>
      {description ? (
        <p className="text-muted-foreground mt-1 max-w-sm text-sm text-pretty">{description}</p>
      ) : null}
      {action ? <div className="mt-5">{action}</div> : null}
    </div>
  )
}

export function ErrorState({
  title = "That didn't load",
  description,
  onRetry,
  className,
}: {
  title?: string
  description?: string
  onRetry?: () => void
  className?: string
}) {
  return (
    <div
      className={cn('flex flex-col items-center px-6 py-14 text-center', className)}
      role="alert"
    >
      <span className="bg-state-blocked-surface text-state-blocked mb-4 grid size-10 place-items-center rounded-lg">
        <AlertCircle className="size-5" />
      </span>
      <p className="text-sm font-semibold">{title}</p>
      {description ? (
        <p className="text-muted-foreground mt-1 max-w-sm text-sm text-pretty">{description}</p>
      ) : null}
      {onRetry ? (
        <Button variant="outline" size="sm" className="mt-5" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </div>
  )
}

/** Rows that match the shape of the table they stand in for. */
export function RowsSkeleton({ rows = 5, className }: { rows?: number; className?: string }) {
  return (
    <div className={cn('divide-border divide-y', className)} aria-hidden>
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="flex items-center gap-4 px-5 py-3.5">
          <Skeleton className="h-4 flex-1" />
          <Skeleton className="hidden h-4 w-24 sm:block" />
          <Skeleton className="h-5 w-20 rounded-full" />
        </div>
      ))}
    </div>
  )
}
