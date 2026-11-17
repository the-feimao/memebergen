<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * @var list<string>
     */
    private const ROLES = ['Editor', 'Super Admin'];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->mapUser($user));

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => self::ROLES,
            'filters' => [
                'search' => $search,
            ],
            'stats' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'admins' => User::query()->whereHas('roles', fn (Builder $query) => $query->where('name', 'Super Admin'))->count(),
            ],
            'currentUserId' => $request->user()?->id,
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles([$request->string('role')->toString()]);
        $user->load('roles:id,name');

        $this->audit->record('user.created', $user, [
            'after' => $this->snapshot($user),
        ], $request->user());

        return redirect()
            ->route('users.index')
            ->with('success', 'User account created.');
    }

    public function update(UpdateManagedUserRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $role = $request->string('role')->toString();
        $isActive = $request->boolean('is_active');

        if ($actor?->is($user) && ! $isActive) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        if ($actor?->is($user) && $role !== 'Super Admin') {
            return back()->withErrors(['role' => 'You cannot remove your own Super Admin role.']);
        }

        $user->loadMissing('roles:id,name');
        $before = $this->snapshot($user);

        if ($before['role'] === 'Super Admin'
            && ($role !== 'Super Admin' || ! $isActive)
            && ! $this->otherActiveSuperAdminExists($user)) {
            return back()->withErrors(['role' => 'At least one active Super Admin must remain.']);
        }

        $user->update([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'is_active' => $isActive,
        ]);

        $user->syncRoles([$role]);
        $user->refresh()->load('roles:id,name');

        $after = $this->snapshot($user);

        if ($before !== $after) {
            $action = match (true) {
                $before['role'] !== $after['role'] => 'user.role_changed',
                $before['is_active'] !== $after['is_active'] => $after['is_active'] ? 'user.activated' : 'user.deactivated',
                default => 'user.updated',
            };

            $this->audit->record($action, $user, [
                'before' => $before,
                'after' => $after,
            ], $actor);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User account updated.');
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        $this->audit->record('user.password_reset_requested', $user, [
            'email' => $user->email,
            'status' => $status,
        ], $request->user());

        return back()->with(
            $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            $status === Password::RESET_LINK_SENT
                ? 'Password reset link sent to '.$user->email.'.'
                : 'A password reset link could not be sent.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'is_active' => $user->is_active,
        ];
    }

    private function otherActiveSuperAdminExists(User $user): bool
    {
        return User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'Super Admin'))
            ->exists();
    }
}
