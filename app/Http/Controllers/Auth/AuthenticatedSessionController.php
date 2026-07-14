<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'Invalid credentials or inactive account.']);
        }

        $request->session()->regenerate();
        $this->auditLogService->log('Login', null, null, ['username' => $credentials['username']]);

        $user = Auth::user();
        $roleName = $user->role?->name;

        $redirectRoute = match ($roleName) {
            'Recorder'         => 'recorder.dashboard',
            'Card Officer'     => 'card-officer.dashboard',
            'OPD Nurse'        => 'opd.dashboard',
            'Admin'            => 'dashboard',
            'Department Head'  => 'dashboard',
            'General Manager'  => 'dashboard',
            default            => 'dashboard',
        };

        return redirect()->intended(route($redirectRoute));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auditLogService->log('Logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
