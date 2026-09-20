<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (self::superExists()) {
            return to_route('login');
        }

        return Inertia::render('auth/Setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if (self::superExists()) {
            return to_route('login');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $super = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'super',
            'status' => 'active',
            'office' => 'System',
            'email_verified_at' => now(),
        ]);

        Cycle::query()->firstOrCreate(
            ['name' => '2026 SGC Functionality Assessment'],
            [
                'level' => 'Public Elementary',
                'opens_at' => now()->toDateString(),
                'deadline_at' => now()->addDays(30)->toDateString(),
                'status' => 'open',
            ]
        );

        Auth::login($super);
        $request->session()->regenerate();

        return to_route('super.users')->with('status', 'Super Admin created. Create a Division Admin next.');
    }

    public static function superExists(): bool
    {
        return User::query()->where('role', 'super')->exists();
    }
}
