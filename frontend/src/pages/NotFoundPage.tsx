import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Panel } from '@/components/panel'
import { EmptyState } from '@/components/states'

export function NotFoundPage() {
  return (
    <div className="grid min-h-dvh place-items-center px-4">
      <div className="w-full max-w-md">
        <Panel>
          <EmptyState
            title="There's nothing at this address"
            description="The page may have moved, or the link was mistyped."
            action={
              <Button asChild>
                <Link to="/dashboard">Back to dashboard</Link>
              </Button>
            }
          />
        </Panel>
      </div>
    </div>
  )
}
