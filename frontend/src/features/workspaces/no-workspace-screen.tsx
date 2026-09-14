import { useState } from 'react'
import { FolderPlus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/states'
import { Panel } from '@/components/panel'
import { CreateWorkspaceDialog } from '@/features/workspaces/create-workspace-dialog'
import { UserMenu } from '@/features/auth/user-menu'

/** What a brand-new account sees: registration deliberately creates nothing. */
export function NoWorkspaceScreen() {
  const [creating, setCreating] = useState(false)

  return (
    <div className="grid min-h-dvh place-items-center px-4 py-10">
      <div className="w-full max-w-md">
        <Panel>
          <EmptyState
            icon={FolderPlus}
            title="Create your first workspace"
            description="A workspace holds your projects, tasks and teammates. You'll be its admin."
            action={<Button onClick={() => setCreating(true)}>New workspace</Button>}
          />
        </Panel>

        <div className="mt-4 flex justify-center">
          <div className="w-48">
            <UserMenu />
          </div>
        </div>
      </div>

      <CreateWorkspaceDialog open={creating} onOpenChange={setCreating} />
    </div>
  )
}
