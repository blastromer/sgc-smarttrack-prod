<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\OperationalReset;
use App\Support\SchoolDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperUserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->orderByRaw("CASE role WHEN 'super' THEN 1 WHEN 'division' THEN 2 WHEN 'school_head' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('super/Users', [
            'title' => 'Users & roles',
            'subtitle' => 'Create Division Admin here. School Heads and Encoders register themselves and wait for accept.',
            'positions' => SchoolDirectory::divisionPositions(),
            'kpis' => [
                ['label' => 'Total users', 'value' => (string) $users->count(), 'hint' => 'All roles'],
                ['label' => 'Super Admin', 'value' => (string) $users->where('role', 'super')->count(), 'hint' => 'System'],
                ['label' => 'Division Admin', 'value' => (string) $users->where('role', 'division')->count(), 'hint' => 'SDO Cadiz City'],
                ['label' => 'School accounts', 'value' => (string) $users->whereIn('role', ['school_head', 'school'])->count(), 'hint' => 'Heads + encoders'],
            ],
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->role_label,
                'office' => $user->office ?: $user->school_name,
                'status' => $user->status,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'office' => 'required|string|max:255',
            'position' => ['required', 'string', Rule::in(SchoolDirectory::divisionPositions())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'division',
            'status' => 'active',
            'office' => $data['office'],
            'position' => $data['position'],
            'email_verified_at' => now(),
        ]);

        return back()->with('status', 'Division Admin created. They can sign in now.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'in:RESET FAT DATA'],
        ], [
            'confirm.in' => 'Type RESET FAT DATA to confirm.',
        ]);

        OperationalReset::run();

        return back()->with('status', 'School accounts, FAT packets, and MOVs were cleared. Super Admin and Division Admin were kept.');
    }
}
