/** Date helpers. All API dates are ISO 8601; due dates are plain Y-m-d. */

const DAY = 86_400_000

export function formatDate(value: string | null | undefined): string {
  if (!value) return ''

  return new Date(value).toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

/** "Just now", "4h ago", "3d ago", then an absolute date once it stops helping. */
export function formatRelative(value: string | null | undefined): string {
  if (!value) return ''

  const then = new Date(value).getTime()
  const elapsed = Date.now() - then

  if (elapsed < 60_000) return 'Just now'
  if (elapsed < 3_600_000) return `${Math.floor(elapsed / 60_000)}m ago`
  if (elapsed < DAY) return `${Math.floor(elapsed / 3_600_000)}h ago`
  if (elapsed < DAY * 7) return `${Math.floor(elapsed / DAY)}d ago`

  return formatDate(value)
}

export interface DueDateState {
  label: string
  tone: 'overdue' | 'soon' | 'normal'
}

/** Due dates carry urgency, so they get a tone as well as a label. */
export function describeDueDate(value: string | null | undefined): DueDateState | null {
  if (!value) return null

  const due = new Date(`${value}T00:00:00`)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const days = Math.round((due.getTime() - today.getTime()) / DAY)

  if (days < 0) return { label: days === -1 ? 'Yesterday' : formatDate(value), tone: 'overdue' }
  if (days === 0) return { label: 'Today', tone: 'soon' }
  if (days === 1) return { label: 'Tomorrow', tone: 'soon' }

  return { label: formatDate(value), tone: 'normal' }
}

export function initials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}
