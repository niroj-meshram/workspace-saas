import type { ReactNode } from 'react'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

/**
 * One labelled control with its message.
 *
 * The error shown is whichever arrived last: Zod's while typing, Laravel's
 * once the request comes back. The server's is the one that decides
 * (PROJECT_SPEC.md §21).
 */
export function Field({
  id,
  label,
  error,
  hint,
  children,
  className,
}: {
  id: string
  label: string
  error?: string
  hint?: string
  children: ReactNode
  className?: string
}) {
  return (
    <div className={cn('space-y-1.5', className)}>
      <Label htmlFor={id} className="text-[0.8125rem]">
        {label}
      </Label>
      {children}
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-destructive text-[0.8125rem]">
          {error}
        </p>
      ) : hint ? (
        <p className="text-muted-foreground text-[0.8125rem]">{hint}</p>
      ) : null}
    </div>
  )
}

/** A message that belongs to the whole form rather than one field. */
export function FormError({ message }: { message?: string | null }) {
  if (!message) return null

  return (
    <p
      role="alert"
      className="bg-state-blocked-surface text-state-blocked rounded-lg px-3 py-2 text-[0.8125rem]"
    >
      {message}
    </p>
  )
}
