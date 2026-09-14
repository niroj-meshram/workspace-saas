<?php

namespace Database\Seeders;

use App\Actions\Invitations\InviteMember;
use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Actions\Tenancy\ChangeMemberRole;
use App\Actions\Tenancy\CreateWorkspace;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A workspace you can sign into and explore.
 *
 * Everything is created through the same actions the API uses, rather than by
 * inserting rows. That costs nothing here and buys two things: the business
 * rules are respected (a task's project really is in its workspace, an
 * assignee really is a member), and the activity feed and dashboard are
 * populated as a side effect, exactly as they would be in use.
 */
class DemoWorkspaceSeeder extends Seeder
{
    /**
     * Shared by every seeded account. Demo data only — see the README.
     */
    public const PASSWORD = 'password';

    /**
     * The account to sign in as. Its presence marks the database as seeded.
     */
    public const PRIMARY_EMAIL = 'admin@example.com';

    public function run(): void
    {
        if (User::where('email', self::PRIMARY_EMAIL)->exists()) {
            $this->command?->warn(
                'Demo data is already present. Rebuild it with: php artisan migrate:fresh --seed'
            );

            return;
        }

        $admin = $this->account('Ada Okafor', self::PRIMARY_EMAIL);
        $engineer = $this->account('Ben Sørensen', 'member@example.com');
        $lead = $this->account('Clara Ruiz', 'lead@example.com');

        $this->seedProductWorkspace($admin, $engineer, $lead);
        $this->seedInternalWorkspace($lead, $admin);

        $this->report();
    }

    private function account(string $name, string $email): User
    {
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make(self::PASSWORD);
        $user->save();

        return $user;
    }

    /**
     * The workspace the primary account administers: enough projects, tasks
     * and history to exercise every screen.
     */
    private function seedProductWorkspace(User $admin, User $engineer, User $lead): void
    {
        $workspace = app(CreateWorkspace::class)->handle($admin, 'Acme Product');

        $this->addMember($workspace, $engineer, WorkspaceRole::User);
        $this->addMember($workspace, $lead, WorkspaceRole::User);

        // A role change, so member.role_changed appears in the feed.
        app(ChangeMemberRole::class)->handle(
            $workspace,
            $admin,
            $workspace->members()->whereBelongsTo($lead)->sole(),
            WorkspaceRole::Admin,
        );

        $billing = $this->project($workspace, $admin, 'Billing rewrite', 'Move invoicing off the legacy service.');
        $mobile = $this->project($workspace, $admin, 'Mobile app', 'iOS and Android parity with the web client.');
        $retired = $this->project($workspace, $admin, '2025 archive', 'Closed out at the end of the year.');

        // Due dates are relative so the overdue and due-soon styling is always
        // visible, whenever the seeder happens to run.
        $this->task($workspace, $engineer, $billing, 'Reconcile invoice totals', [
            'assignee_id' => $admin->getKey(),
            'priority' => TaskPriority::High,
            'due_date' => now()->subDays(2)->toDateString(),
        ], TaskStatus::InProgress);

        $this->task($workspace, $engineer, $billing, 'Audit card vault access', [
            'assignee_id' => $lead->getKey(),
            'priority' => TaskPriority::High,
            'due_date' => now()->toDateString(),
        ], TaskStatus::Blocked);

        $this->task($workspace, $admin, $billing, 'Draft the migration plan', [
            'assignee_id' => $admin->getKey(),
            'due_date' => now()->addDays(9)->toDateString(),
        ]);

        $this->task($workspace, $engineer, $billing, 'Retire the legacy webhook', [
            'priority' => TaskPriority::Low,
        ], TaskStatus::Done);

        $this->task($workspace, $lead, $mobile, 'Push notification permissions', [
            'assignee_id' => $engineer->getKey(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->task($workspace, $lead, $mobile, 'Offline cache for the task list', [
            'assignee_id' => $admin->getKey(),
            'priority' => TaskPriority::Low,
        ]);

        $this->task($workspace, $engineer, $mobile, 'Ship release notes', [], TaskStatus::Done);

        // Archived last, so its tasks exist first and stay readable.
        $this->task($workspace, $admin, $retired, 'Close out the Q4 ledger', [], TaskStatus::Done);
        app(ArchiveProject::class)->handle($workspace, $admin, $retired);

        // example.com is reserved by RFC 2606, so a seeded invitation can
        // never reach a real inbox even with a live mail provider configured.
        app(InviteMember::class)->handle($workspace, $admin, 'newcomer@example.com', WorkspaceRole::User);
    }

    /**
     * A second workspace where the primary account is only a member, so the
     * difference between the admin and member interface is visible by
     * switching workspace.
     */
    private function seedInternalWorkspace(User $owner, User $admin): void
    {
        $workspace = app(CreateWorkspace::class)->handle($owner, 'Internal Tools');

        $this->addMember($workspace, $admin, WorkspaceRole::User);

        $project = $this->project($workspace, $owner, 'Design system', 'Shared components for every internal app.');

        $this->task($workspace, $owner, $project, 'Document the colour tokens', [
            'assignee_id' => $admin->getKey(),
            'priority' => TaskPriority::High,
            'due_date' => now()->addDays(4)->toDateString(),
        ], TaskStatus::InProgress);

        $this->task($workspace, $owner, $project, 'Audit icon usage', [
            'assignee_id' => $admin->getKey(),
        ]);
    }

    private function addMember(Workspace $workspace, User $user, WorkspaceRole $role): WorkspaceMember
    {
        $member = new WorkspaceMember;
        $member->user_id = $user->getKey();
        $member->role = $role;

        $workspace->members()->save($member);

        return $member;
    }

    private function project(Workspace $workspace, User $actor, string $name, string $description): Project
    {
        return app(CreateProject::class)->handle($workspace, $actor, $name, $description);
    }

    /**
     * Create a task, then move it to its final status through UpdateTask, so
     * the transition is recorded the way a real one would be.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function task(
        Workspace $workspace,
        User $actor,
        Project $project,
        string $title,
        array $attributes = [],
        TaskStatus $status = TaskStatus::Todo,
    ): void {
        $task = app(CreateTask::class)->handle($workspace, $actor, array_merge([
            'title' => $title,
            'project_id' => $project->getKey(),
        ], $attributes));

        if ($status !== TaskStatus::Todo) {
            app(UpdateTask::class)->handle($workspace, $actor, $task, ['status' => $status]);
        }
    }

    private function report(): void
    {
        $this->command?->newLine();
        $this->command?->info('Seeded two workspaces. Sign in with any of:');
        $this->command?->table(
            ['Email', 'Password', 'Acme Product', 'Internal Tools'],
            [
                [self::PRIMARY_EMAIL, self::PASSWORD, 'admin', 'member'],
                ['lead@example.com', self::PASSWORD, 'admin', 'admin'],
                ['member@example.com', self::PASSWORD, 'member', '—'],
            ],
        );
    }
}
