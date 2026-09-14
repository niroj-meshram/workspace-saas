import { Outlet } from 'react-router'

/**
 * The signed-out shell.
 *
 * A single centred column on a quiet ground: nothing to navigate yet, so the
 * page gives the form the whole stage rather than dressing it with marketing.
 */
export function AuthLayout() {
  return (
    <div className="grid min-h-dvh place-items-center px-4 py-10">
      <div className="w-full max-w-[25rem]">
        <div className="mb-8 flex items-center gap-2.5">
          <span
            className="bg-primary text-primary-foreground grid size-7 place-items-center rounded-md text-xs font-semibold"
            aria-hidden
          >
            W
          </span>
          <span className="text-[0.9375rem] font-semibold tracking-tight">Workspace</span>
        </div>

        <Outlet />
      </div>
    </div>
  )
}
