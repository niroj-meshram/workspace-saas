import { describeActivity } from '@/features/activity/describe-activity'
import { formatRelative, initials } from '@/lib/format'
import type { Activity } from '@/types/api'

export function ActivityLine({ activity }: { activity: Activity }) {
  const actor = activity.user?.name ?? 'Someone'

  return (
    <li className="flex items-start gap-3 px-5 py-3">
      <span
        className="bg-accent text-accent-foreground mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-[0.625rem] font-semibold"
        aria-hidden
      >
        {activity.user ? initials(activity.user.name) : '—'}
      </span>
      <p className="min-w-0 text-sm text-pretty break-words">
        <span className="font-medium">{actor}</span>{' '}
        <span className="text-muted-foreground">{describeActivity(activity)}</span>{' '}
        <time
          dateTime={activity.created_at ?? undefined}
          className="text-muted-foreground text-xs whitespace-nowrap"
        >
          {formatRelative(activity.created_at)}
        </time>
      </p>
    </li>
  )
}
