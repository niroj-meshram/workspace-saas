import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/**
 * The one surface in the app.
 *
 * Sections are separated by a hairline and generous padding rather than by
 * elevation, so nothing floats except things that genuinely overlay the page.
 * Radius is deliberately tiered: this panel is the widest, controls inside it
 * are tighter, state chips are pills.
 */
export function Panel({
  className,
  label,
  children,
}: {
  className?: string
  /** Names the region, for panels that need to be told apart. */
  label?: string
  children: ReactNode
}) {
  return (
    // min-w-0: a grid or flex child defaults to min-width:auto and would
    // refuse to shrink below its content, pushing the page sideways on a phone.
    <section
      aria-label={label}
      className={cn('bg-card border-border min-w-0 rounded-xl border', className)}
    >
      {children}
    </section>
  )
}

export function PanelHeader({
  title,
  action,
  className,
}: {
  title: ReactNode
  action?: ReactNode
  className?: string
}) {
  return (
    <header
      className={cn(
        'border-border flex items-center justify-between gap-3 border-b px-5 py-3.5',
        className,
      )}
    >
      <h2 className="text-[0.9375rem] font-semibold tracking-tight">{title}</h2>
      {action}
    </header>
  )
}
