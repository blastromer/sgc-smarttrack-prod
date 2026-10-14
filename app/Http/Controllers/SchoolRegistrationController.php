<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SchoolRegistrationApproved;
use App\Notifications\SchoolRegistrationSubmitted;
use App\Support\SchoolDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $encoders = User::query()
            ->where('role', 'school')
            ->whereIn('status', ['active', 'archived'])
            ->where('school_code', $head->school_code)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->row($user));

        return Inertia::render('school/Encoders', [
            'title' => 'Encoders',
            'subtitle' => 'Teachers who encode and upload MOVs for '.($head->school_name ?: 'this school').'.',
            'kpis' => [
                [
                    'label' => 'Active encoders',
                    'value' => (string) $encoders->where('status', 'active')->count(),
                    'hint' => 'Can encode and upload MOVs',
                    'tone' => $encoders->where('status', 'active')->isEmpty() ? 'warn' : null,
                ],
                [
                    'label' => 'Pending encoders',
                    'value' => (string) $pending->count(),
                    'hint' => 'Waiting for School Head accept',
                    'tone' => $pending->isNotEmpty() ? 'warn' : null,
                ],
            ],
            'pending' => $pending,
            'encoders' => $encoders,
            'positions' => SchoolDirectory::encoderPositions(),
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

    public function updateEncoder(Request $request, User $user): RedirectResponse
    {
        $this->encoderForHead($request->user(), $user);

        $positions = SchoolDirectory::encoderPositions();
        if (filled($user->position) && ! in_array($user->position, $positions, true)) {
            $positions[] = $user->position;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'position' => ['required', 'string', Rule::in($positions)],
        ], [
            'position.in' => 'Select a position from the list.',
        ]);

        $user->update($data);

        return back()->with('status', $user->name.' was updated.');
    }

    public function updateEncoderPassword(Request $request, User $user): RedirectResponse
    {
        $this->encoderForHead($request->user(), $user);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $data['password']]);

        return back()->with('status', 'Password updated for '.$user->name.'.');
    }

    public function archiveEncoder(Request $request, User $user): RedirectResponse
    {
        $this->encoderForHead($request->user(), $user);
        abort_unless($user->status === 'active', 404);

        $user->update(['status' => 'archived']);

        return back()->with('status', $user->name.' is archived and can no longer sign in.');
    }

    public function restoreEncoder(Request $request, User $user): RedirectResponse
    {
        $this->encoderForHead($request->user(), $user);
        abort_unless($user->status === 'archived', 404);

        $user->update(['status' => 'active']);

        return back()->with('status', $user->name.' is active again.');
    }

    private function encoderForHead(?User $head, User $user): void
    {
        abort_unless($head?->isSchoolHead(), 403);
        abort_unless(
            $user->isEncoder()
            && $user->school_code === $head->school_code
            && in_array($user->status, ['active', 'archived'], true),
            404
        );
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
            'status' => $user->status,
            'role_label' => $user->role_label,
            'requested' => $user->created_at?->format('M j, Y g:i A'),
            'joined' => $user->email_verified_at?->format('M j, Y') ?: $user->updated_at?->format('M j, Y'),
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
