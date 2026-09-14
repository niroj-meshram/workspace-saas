import { useNavigate } from 'react-router'
import { LogOut } from 'lucide-react'
import { toast } from 'sonner'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/features/auth/auth-provider'
import { initials } from '@/lib/format'

export function UserMenu() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  if (!user) return null

  const signOut = async () => {
    try {
      await logout()
      navigate('/login', { replace: true })
    } catch {
      toast.error("Couldn't sign you out. Try again.")
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-auto w-full justify-start gap-2.5 px-2 py-2">
          <span
            className="bg-accent text-accent-foreground grid size-6 shrink-0 place-items-center rounded-full text-[0.6875rem] font-semibold"
            aria-hidden
          >
            {initials(user.name)}
          </span>
          <span className="min-w-0 text-left">
            <span className="block truncate text-sm font-medium">{user.name}</span>
          </span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-56">
        <DropdownMenuLabel className="font-normal">
          <span className="block text-sm font-medium">{user.name}</span>
          <span className="text-muted-foreground block truncate text-xs">{user.email}</span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={signOut} className="gap-2">
          <LogOut className="size-4" />
          Sign out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
