import { Loader2 } from 'lucide-react'

export function FullPageSpinner({ label = 'Loading' }: { label?: string }) {
  return (
    <div className="grid min-h-dvh place-items-center" role="status" aria-live="polite">
      <div className="text-muted-foreground flex flex-col items-center gap-3">
        <Loader2 className="size-5 animate-spin" />
        <p className="text-sm">{label}</p>
      </div>
    </div>
  )
}
