<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff role management (no user creation). Gated by `users.manage`. Several
 * guards prevent privilege escalation and self-lockout:
 *  - you cannot change your own role or deactivate yourself,
 *  - the owner account cannot be demoted or deactivated here,
 *  - only an owner may grant the owner role.
 */
class StaffController extends Controller
{
    /** @return list<string> */
    private function roles(): array
    {
        return array_keys((array) config('rbac.roles'));
    }

    public function index(Request $request): Response
    {
        $actor = $request->user();

        $staff = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->getRoleNames()->first(),
                'is_active' => $u->is_active,
                'is_self' => $actor !== null && $u->id === $actor->id,
                'is_owner' => $u->hasRole('owner'),
            ])
            ->all();

        return Inertia::render('staff/index', [
            'staff' => $staff,
            'roles' => $this->roles(),
            'canAssignOwner' => $actor?->hasRole('owner') ?? false,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in($this->roles())],
        ]);
        $role = (string) $data['role'];

        // Self-lockout + owner-protection + owner-grant guards.
        abort_if($user->id === $actor->id, 403, 'You cannot change your own role.');
        abort_if($user->hasRole('owner'), 403, 'The owner role cannot be changed here.');
        abort_if($role === 'owner' && ! $actor->hasRole('owner'), 403, 'Only the owner can grant the owner role.');

        $user->syncRoles([$role]);

        activity('User')
            ->performedOn($user)
            ->event('role-changed')
            ->withProperties(['role' => $role])
            ->log('Staff role changed to '.$role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        abort_if($user->id === $actor->id, 403, 'You cannot deactivate yourself.');
        abort_if($user->hasRole('owner'), 403, 'The owner account cannot be deactivated.');

        $user->is_active = (bool) $data['is_active'];
        $user->save();

        activity('User')
            ->performedOn($user)
            ->event($user->is_active ? 'activated' : 'deactivated')
            ->log('Staff '.($user->is_active ? 'activated' : 'deactivated'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? __('Staff activated.') : __('Staff deactivated.'),
        ]);

        return back();
    }
}
