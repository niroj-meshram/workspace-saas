import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router'
import {
  Activity,
  FolderKanban,
  LayoutDashboard,
  ListChecks,
  Menu,
  Settings,
  Users,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { WorkspaceSwitcher } from '@/features/workspaces/workspace-switcher'
import { UserMenu } from '@/features/auth/user-menu'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { WorkspaceGate } from '@/features/workspaces/workspace-gate'
import { cn } from '@/lib/utils'

const NAV = [
  { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/projects', label: 'Projects', icon: FolderKanban },
  { to: '/tasks', label: 'Tasks', icon: ListChecks },
  { to: '/members', label: 'Members', icon: Users },
  { to: '/activity', label: 'Activity', icon: Activity },
  { to: '/settings', label: 'Settings', icon: Settings },
]

function Nav({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <nav className="flex flex-col gap-0.5">
      {NAV.map(({ to, label, icon: Icon }) => (
        <NavLink
          key={to}
          to={to}
          onClick={onNavigate}
          className={({ isActive }) =>
            cn(
              'flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors',
              isActive
                ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                : 'text-muted-foreground hover:bg-sidebar-accent/60 hover:text-foreground',
            )
          }
        >
          <Icon className="size-4 shrink-0" />
          {label}
        </NavLink>
      ))}
    </nav>
  )
}

function SidebarContents({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <div className="flex h-full flex-col gap-4 p-3">
      <WorkspaceSwitcher />
      <Nav onNavigate={onNavigate} />
      <div className="mt-auto">
        <UserMenu />
      </div>
    </div>
  )
}

/**
 * The application shell.
 *
 * The sidebar sits on the ground colour and the content is an inset panel, so
 * the page reads as one surface with a working area rather than a tray of
 * floating cards.
 */
export function AppLayout() {
  return (
    <WorkspaceGate>
      <AppShell />
    </WorkspaceGate>
  )
}

function AppShell() {
  const { workspace } = useWorkspace()
  const [mobileOpen, setMobileOpen] = useState(false)
  const location = useLocation()

  return (
    <div className="min-h-dvh lg:flex">
      <aside className="bg-sidebar border-sidebar-border hidden w-60 shrink-0 border-r lg:block">
        <div className="sticky top-0 h-dvh">
          <SidebarContents />
        </div>
      </aside>

      <header className="bg-sidebar border-sidebar-border sticky top-0 z-20 flex items-center gap-2 border-b px-3 py-2 lg:hidden">
        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
          <SheetTrigger asChild>
            <Button variant="ghost" size="icon" aria-label="Open menu">
              <Menu className="size-4" />
            </Button>
          </SheetTrigger>
          <SheetContent side="left" className="w-64 p-0">
            <SheetTitle className="sr-only">Navigation</SheetTitle>
            <SidebarContents onNavigate={() => setMobileOpen(false)} />
          </SheetContent>
        </Sheet>

        <span className="truncate text-sm font-medium">{workspace!.name}</span>
      </header>

      <main className="min-w-0 flex-1">
        {/* Remount page state when the workspace changes. */}
        <div
          key={`${workspace!.id}:${location.pathname}`}
          className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10"
        >
          <Outlet />
        </div>
      </main>
    </div>
  )
}
