import { useState } from 'react'
import { MailPlus, MoreHorizontal, Trash2, UserCog, Users, XCircle } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Panel, PanelHeader } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { EmptyState, ErrorState, RowsSkeleton } from '@/components/states'
import { RoleBadge } from '@/components/role-badge'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { InviteDialog } from '@/features/members/invite-dialog'
import { useChangeMemberRole, useMembers, useRemoveMember } from '@/features/members/hooks'
import { useInvitations, useRevokeInvitation } from '@/features/invitations/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { useAuth } from '@/features/auth/auth-provider'
import { formatDate, initials } from '@/lib/format'
import { toApiError } from '@/lib/api-error'
import type { Invitation, Member } from '@/types/api'

export function MembersPage() {
  const { workspace, isAdmin } = useWorkspace()
  const { user } = useAuth()
  const workspaceId = workspace!.id

  const members = useMembers(workspaceId)
  const invitations = useInvitations(workspaceId, isAdmin)
  const changeRole = useChangeMemberRole(workspaceId)
  const removeMember = useRemoveMember(workspaceId)
  const revokeInvitation = useRevokeInvitation(workspaceId)

  const [inviting, setInviting] = useState(false)
  const [removing, setRemoving] = useState<Member | null>(null)
  const [revoking, setRevoking] = useState<Invitation | null>(null)

  const list = members.data ?? []
  // A workspace must always keep one admin, so the UI stops offering the two
  // moves that would empty the role. The API refuses them regardless; this
  // only avoids presenting a button that is guaranteed to fail.
  const adminCount = list.filter((member) => member.role === 'admin').length

  const isLastAdmin = (member: Member) => member.role === 'admin' && adminCount <= 1

  const onChangeRole = async (member: Member, role: 'admin' | 'user') => {
    try {
      await changeRole.mutateAsync({ memberId: member.id, role })
      toast.success(
        `${member.user?.name ?? 'Member'} is now ${role === 'admin' ? 'an admin' : 'a member'}`,
      )
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const confirmRemove = async () => {
    if (!removing) return

    try {
      await removeMember.mutateAsync(removing.id)
      toast.success(`${removing.user?.name ?? 'Member'} removed`)
      setRemoving(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const confirmRevoke = async () => {
    if (!revoking) return

    try {
      await revokeInvitation.mutateAsync(revoking.id)
      toast.success('Invitation withdrawn')
      setRevoking(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const pending = (invitations.data ?? []).filter((invitation) => invitation.status === 'pending')

  return (
    <div className="space-y-6">
      <PageHeader
        title="Members"
        description="Who can see this workspace, and what they're allowed to do."
        action={
          isAdmin ? (
            <Button onClick={() => setInviting(true)}>
              <MailPlus className="size-4" />
              Invite
            </Button>
          ) : null
        }
      />

      <Panel>
        {members.isPending ? (
          <RowsSkeleton />
        ) : members.isError ? (
          <ErrorState description="Members didn't load." onRetry={members.refetch} />
        ) : list.length === 0 ? (
          <EmptyState icon={Users} title="No members yet" />
        ) : (
          <ul className="divide-border divide-y">
            {list.map((member) => {
              const isSelf = member.user?.id === user?.id
              const lastAdmin = isLastAdmin(member)

              return (
                <li key={member.id} className="flex items-center gap-3 px-5 py-3.5">
                  <span
                    className="bg-accent text-accent-foreground grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold"
                    aria-hidden
                  >
                    {initials(member.user?.name ?? '?')}
                  </span>

                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">
                      {member.user?.name ?? 'Unknown'}
                      {isSelf ? <span className="text-muted-foreground"> (you)</span> : null}
                    </p>
                    <p className="text-muted-foreground truncate text-xs">{member.user?.email}</p>
                  </div>

                  <RoleBadge role={member.role} />

                  {isAdmin ? (
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label={`Actions for ${member.user?.name ?? 'member'}`}
                        >
                          <MoreHorizontal className="size-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-56">
                        {member.role === 'user' ? (
                          <DropdownMenuItem
                            onSelect={() => onChangeRole(member, 'admin')}
                            className="gap-2"
                          >
                            <UserCog className="size-4" />
                            Make admin
                          </DropdownMenuItem>
                        ) : (
                          <DropdownMenuItem
                            disabled={lastAdmin}
                            onSelect={() => onChangeRole(member, 'user')}
                            className="gap-2"
                          >
                            <UserCog className="size-4" />
                            {lastAdmin ? 'Only admin — keep as admin' : 'Change to member'}
                          </DropdownMenuItem>
                        )}

                        <DropdownMenuItem
                          disabled={lastAdmin}
                          onSelect={() => setRemoving(member)}
                          className="text-destructive gap-2"
                        >
                          <Trash2 className="size-4" />
                          {lastAdmin ? 'Only admin — cannot remove' : 'Remove from workspace'}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  ) : null}
                </li>
              )
            })}
          </ul>
        )}
      </Panel>

      {isAdmin ? (
        <Panel>
          <PanelHeader title="Pending invitations" />

          {invitations.isPending ? (
            <RowsSkeleton rows={2} />
          ) : invitations.isError ? (
            <ErrorState description="Invitations didn't load." onRetry={invitations.refetch} />
          ) : pending.length === 0 ? (
            <EmptyState
              icon={MailPlus}
              title="Nobody waiting"
              description="Invitations you send appear here until they're accepted."
            />
          ) : (
            <ul className="divide-border divide-y">
              {pending.map((invitation) => (
                <li key={invitation.id} className="flex items-center gap-3 px-5 py-3.5">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{invitation.email}</p>
                    <p className="text-muted-foreground text-xs">
                      Invited as {invitation.role === 'admin' ? 'admin' : 'member'}, expires{' '}
                      {formatDate(invitation.expires_at)}
                    </p>
                  </div>

                  <Button variant="ghost" size="sm" onClick={() => setRevoking(invitation)}>
                    <XCircle className="size-4" />
                    Withdraw
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      ) : null}

      <InviteDialog workspaceId={workspaceId} open={inviting} onOpenChange={setInviting} />

      <ConfirmDialog
        open={Boolean(removing)}
        onOpenChange={(open) => !open && setRemoving(null)}
        title={`Remove ${removing?.user?.name ?? 'this member'}?`}
        description="They lose access to this workspace immediately. Their past activity stays in the history, and they can be invited back."
        confirmLabel="Remove member"
        onConfirm={confirmRemove}
        isPending={removeMember.isPending}
      />

      <ConfirmDialog
        open={Boolean(revoking)}
        onOpenChange={(open) => !open && setRevoking(null)}
        title="Withdraw this invitation?"
        description={`The link sent to ${revoking?.email ?? 'them'} stops working. You can invite the same address again afterwards.`}
        confirmLabel="Withdraw invitation"
        onConfirm={confirmRevoke}
        isPending={revokeInvitation.isPending}
      />
    </div>
  )
}
