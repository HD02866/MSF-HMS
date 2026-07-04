<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CardRoom\Requests\StoreUserRequest;
use App\Modules\CardRoom\Requests\UpdateUserRequest;
use App\Services\AuditLogService;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReferenceDataService $ref,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with(['role:id,name', 'department:id,name'])
                ->orderBy('full_name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles'       => $this->ref->roles(),
            'departments' => $this->ref->activeDepartments(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'full_name'     => $data['full_name'],
            'username'      => $data['username'],
            'password'      => $data['password'],
            'role_id'       => $data['role_id'],
            'department_id' => $data['department_id'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'is_active'     => true,
        ]);

        $this->auditLogService->log('User Created', $user, null, $user->fresh()->toArray());

        return redirect()
            ->route('users.index')
            ->with('success', "User created successfully. {$user->full_name} can now sign in.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user'        => $user->load(['role:id,name', 'department:id,name']),
            'roles'       => $this->ref->roles(),
            'departments' => $this->ref->activeDepartments(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $old  = $user->toArray();
        $user->update($data);
        $this->auditLogService->log('User Updated', $user, $old, $user->fresh()->toArray());

        return redirect()
            ->route('users.index')
            ->with('success', "User updated successfully. {$user->full_name} has been saved.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $old = $user->toArray();
        $user->update(['is_active' => false]);
        $this->auditLogService->log('User Deactivated', $user, $old, $user->fresh()->toArray());

        return redirect()
            ->route('users.index')
            ->with('success', "User deactivated successfully. {$user->full_name} can no longer sign in.");
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'user' => $request->user()->load(['role:id,name', 'department:id,name']),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $old  = $user->toArray();
        $user->update($data);
        $this->auditLogService->log('Profile Updated', $user, $old, $user->fresh()->toArray());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $this->auditLogService->log('Password Changed', $user);

        return back()->with('success', 'Password updated successfully.');
    }
}
