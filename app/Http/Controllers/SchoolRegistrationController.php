<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SchoolRegistrationApproved;
use App\Notifications\SchoolRegistrationSubmitted;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SchoolRegistrationController extends Controller
{
    public function index(): Response
    {
        $pending = User::query()
            ->where('role', 'school_head')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->row($user));

        return Inertia::render('division/Registrations', [
            'title' => 'School Head registrations',
            'subtitle' => 'Accept School Heads before they can sign in and approve their teachers.',
            'kpis' => [
                [
                    'label' => 'Pending School Heads',
                    'value' => (string) $pending->count(),
                    'hint' => 'Waiting for Division accept',
                    'tone' => $pending->isNotEmpty() ? 'warn' : null,
                ],
            ],
            'registrations' => $pending,
            'accept_route' => 'division.registrations.accept',
            'empty_text' => 'No pending School Head requests.',
        ]);
    }

    public function accept(User $user): RedirectResponse
    {
        abort_unless($user->isSchoolHead() && $user->status === 'pending', 404);

        $this->activate($user);

        User::query()
            ->where('role', 'division')
            ->get()
            ->each(function (User $admin) use ($user) {
                $admin->unreadNotifications
                    ->where('type', SchoolRegistrationSubmitted::class)
                    ->filter(fn ($notification) => (int) ($notification->data['school_user_id'] ?? 0) === $user->id)
                    ->each->markAsRead();
            });

        return back()->with('status', $user->name.' is now an active School Head.');
    }

    public function encoders(): Response
    {
        $head = request()->user();
        abort_unless($head->isSchoolHead(), 403);

        $pending = User::query()
            ->where('role', 'school')
            ->where('status', 'pending')
            ->where('school_code', $head->school_code)
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->row($user));

        return Inertia::render('division/Registrations', [
            'title' => 'Encoder registrations',
            'subtitle' => 'Accept teachers who will encode and upload MOVs for '.$head->school_name.'.',
            'kpis' => [
                [
                    'label' => 'Pending encoders',
                    'value' => (string) $pending->count(),
                    'hint' => 'Waiting for School Head accept',
                    'tone' => $pending->isNotEmpty() ? 'warn' : null,
                ],
            ],
            'registrations' => $pending,
            'accept_route' => 'school.encoders.accept',
            'empty_text' => 'No pending Encoder requests for this school.',
        ]);
    }

    public function acceptEncoder(User $user): RedirectResponse
    {
        $head = request()->user();
        abort_unless($head->isSchoolHead(), 403);
        abort_unless(
            $user->isEncoder()
            && $user->status === 'pending'
            && $user->school_code === $head->school_code,
            404
        );

        $this->activate($user);

        $head->unreadNotifications
            ->where('type', SchoolRegistrationSubmitted::class)
            ->filter(fn ($notification) => (int) ($notification->data['school_user_id'] ?? 0) === $user->id)
            ->each->markAsRead();

        return back()->with('status', $user->name.' is now an active Encoder for your school.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'school_name' => $user->school_name,
            'school_code' => $user->school_code,
            'position' => $user->position,
            'role_label' => $user->role_label,
            'requested' => $user->created_at?->format('M j, Y g:i A'),
        ];
    }

    private function activate(User $user): void
    {
        $user->update([
            'status' => 'active',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        $user->notify(new SchoolRegistrationApproved);
    }
}
