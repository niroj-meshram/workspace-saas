import { Suspense, lazy } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router'
import { QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from '@/components/ui/sonner'
import { queryClient } from '@/lib/query-client'
import { AuthProvider } from '@/features/auth/auth-provider'
import { WorkspaceProvider } from '@/features/workspaces/workspace-provider'
import { GuestRoute, ProtectedRoute } from '@/components/protected-route'
import { AuthLayout } from '@/layouts/auth-layout'
import { AppLayout } from '@/layouts/app-layout'
import { FullPageSpinner } from '@/components/full-page-spinner'
import { LoginPage } from '@/pages/LoginPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { AcceptInvitationPage } from '@/pages/AcceptInvitationPage'
import { NotFoundPage } from '@/pages/NotFoundPage'

/*
 * Signed-in screens load on demand. Sign-in, registration and the invitation
 * link stay in the first bundle: they are the entry points, and a spinner on
 * the way to a login form would be a step backwards.
 */
const DashboardPage = lazy(() =>
  import('@/pages/DashboardPage').then((m) => ({ default: m.DashboardPage })),
)
const ProjectsPage = lazy(() =>
  import('@/pages/ProjectsPage').then((m) => ({ default: m.ProjectsPage })),
)
const ProjectDetailPage = lazy(() =>
  import('@/pages/ProjectDetailPage').then((m) => ({ default: m.ProjectDetailPage })),
)
const TasksPage = lazy(() => import('@/pages/TasksPage').then((m) => ({ default: m.TasksPage })))
const MembersPage = lazy(() =>
  import('@/pages/MembersPage').then((m) => ({ default: m.MembersPage })),
)
const ActivityPage = lazy(() =>
  import('@/pages/ActivityPage').then((m) => ({ default: m.ActivityPage })),
)
const SettingsPage = lazy(() =>
  import('@/pages/SettingsPage').then((m) => ({ default: m.SettingsPage })),
)

/** Routes from PROJECT_SPEC.md §22. */
export function AppRoutes() {
  return (
    <Routes>
      <Route element={<GuestRoute />}>
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
        </Route>
      </Route>

      {/* Public link, private action: the page itself sends guests to sign in. */}
      <Route path="/invitations/:token" element={<AcceptInvitationPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<WorkspaceShell />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/projects" element={<ProjectsPage />} />
          <Route path="/projects/:projectId" element={<ProjectDetailPage />} />
          <Route path="/tasks" element={<TasksPage />} />
          <Route path="/members" element={<MembersPage />} />
          <Route path="/activity" element={<ActivityPage />} />
          <Route path="/settings" element={<SettingsPage />} />
        </Route>
      </Route>

      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}

/** Workspaces are only ever loaded for a signed-in user. */
function WorkspaceShell() {
  return (
    <WorkspaceProvider>
      <Suspense fallback={<FullPageSpinner />}>
        <AppLayout />
      </Suspense>
    </WorkspaceProvider>
  )
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </BrowserRouter>
      <Toaster position="bottom-right" />
    </QueryClientProvider>
  )
}
