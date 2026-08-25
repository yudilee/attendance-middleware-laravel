<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Display a listing of admin and staff users along with session management.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $role = $request->input('role');

        $query = User::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($role) && $role !== 'all') {
            $query->where('role', $role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Fetch active browser sessions for the current authenticated user
        $sessions = [];
        if (config('session.driver') === 'database' && Auth::check()) {
            $rawSessions = DB::table('sessions')
                ->where('user_id', Auth::id())
                ->orderBy('last_activity', 'desc')
                ->get();

            $currentSessionId = $request->session()->getId();

            foreach ($rawSessions as $s) {
                $agent = $s->user_agent ?? 'Unknown Browser';
                $isCurrent = ($s->id === $currentSessionId);
                
                $sessions[] = [
                    'id' => $s->id,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $agent,
                    'is_current_device' => $isCurrent,
                    'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                ];
            }
        } elseif (Auth::check()) {
            // Fallback for Redis / File session driver
            $sessions[] = [
                'id' => $request->session()->getId(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? 'Current Browser',
                'is_current_device' => true,
                'last_active' => 'Just now',
            ];
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search ?? '',
                'role' => $role ?? 'all',
            ],
            'sessions' => $sessions,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'string', Password::defaults()],
            'role' => 'required|string|in:admin,manager,hr,viewer',
            'is_active' => 'boolean',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', "User account '{$validated['name']}' created successfully.");
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|string|email|max:255|unique:users,email,{$user->id}",
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => 'required|string|in:admin,manager,hr,viewer',
            'is_active' => 'boolean',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->is_active = $validated['is_active'] ?? true;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->back()->with('success', "User account '{$user->name}' updated successfully.");
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return redirect()->back()->with('error', 'You cannot delete your own active administrator account.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->back()->with('success', "User account '{$userName}' deleted successfully.");
    }

    /**
     * Revoke a specific active browser session.
     */
    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', Auth::id())
                ->where('id', $sessionId)
                ->delete();
        }

        return redirect()->back()->with('success', 'Browser session revoked successfully.');
    }

    /**
     * Logout other browser sessions.
     */
    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $currentSessionId = $request->session()->getId();

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', Auth::id())
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }

        return redirect()->back()->with('success', 'All other active browser sessions have been logged out.');
    }
}
