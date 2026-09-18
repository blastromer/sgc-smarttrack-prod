<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SchoolRegistrationSubmitted;
use App\Support\SchoolDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register', [
            'positions' => SchoolDirectory::positionsByRole(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'school_name' => trim((string) $request->input('school_name')),
            'school_code' => trim((string) $request->input('school_code')),
        ]);

        $isHead = $request->input('role') === 'school_head';

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'role' => 'required|in:school,school_head',
            'school_name' => array_values(array_filter([
                'required',
                'string',
                'max:255',
                $isHead ? Rule::unique('users', 'school_name')->where(fn ($query) => $query->where('role', 'school_head')) : null,
            ])),
            'school_code' => array_values(array_filter([
                'required',
                'string',
                'max:32',
                $isHead ? Rule::unique('users', 'school_code')->where(fn ($query) => $query->where('role', 'school_head')) : null,
            ])),
            'position' => ['required', 'string', Rule::in(SchoolDirectory::positionsFor($request->input('role')))],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'school_name.unique' => 'This school name is already registered. Use a unique school name, or register as Encoder if you belong to that school.',
            'school_code.unique' => 'This School ID is already registered. Use a unique School ID, or register as Encoder with the School Head\'s School ID.',
            'position.in' => 'Select a position from the list.',
        ]);

        $head = User::query()
            ->where('role', 'school_head')
            ->where('status', 'active')
            ->where('school_code', $request->school_code)
            ->first();

        if ($request->role === 'school' && ! $head) {
            throw ValidationException::withMessages([
                'school_code' => 'No active School Head is registered for this School ID yet. The School Head must register and be accepted by Division first.',
            ]);
        }

        if ($request->role === 'school' && strcasecmp($request->school_name, (string) $head->school_name) !== 0) {
            throw ValidationException::withMessages([
                'school_name' => 'School name must match the registered school for this School ID: '.$head->school_name.'.',
            ]);
        }

        $applicant = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'status' => 'pending',
            'school_name' => $head?->school_name ?: $request->school_name,
            'school_code' => $request->school_code,
            'position' => $request->position,
        ]);

        if ($applicant->isSchoolHead()) {
            $reviewers = User::query()->where('role', 'division')->where('status', 'active')->get();
        } else {
            $reviewers = User::query()
                ->where('role', 'school_head')
                ->where('status', 'active')
                ->where('school_code', $applicant->school_code)
                ->get();
        }

        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new SchoolRegistrationSubmitted($applicant));
        }

        return to_route('register.pending')->with(
            'approver',
            $applicant->isSchoolHead() ? 'division' : 'school_head'
        );
    }

    public function pending(): Response
    {
        return Inertia::render('auth/Pending', [
            'approver' => session('approver', 'division'),
        ]);
    }
}
