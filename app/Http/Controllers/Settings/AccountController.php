<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SchoolDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Account', [
            'title' => 'Account',
            'subtitle' => 'Your profile and school details',
            'positions' => SchoolDirectory::positionsByRole(),
        ]);
    }

    public function docs(): Response
    {
        return Inertia::render('Docs', [
            'title' => 'Docs',
            'subtitle' => 'SGC FAT rules used in this portal',
        ]);
    }

    public function help(): Response
    {
        return Inertia::render('Help', [
            'title' => 'Help',
            'subtitle' => 'How to encode, submit, and fix a returned MOV',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ];

        if ($user->isSchoolStaff()) {
            $rules['school_name'] = array_values(array_filter([
                'required',
                'string',
                'max:255',
                $user->isSchoolHead()
                    ? Rule::unique('users', 'school_name')->where(fn ($query) => $query->where('role', 'school_head'))->ignore($user->id)
                    : null,
            ]));
            $rules['school_code'] = array_values(array_filter([
                'nullable',
                'string',
                'max:32',
                $user->isSchoolHead()
                    ? Rule::unique('users', 'school_code')->where(fn ($query) => $query->where('role', 'school_head'))->ignore($user->id)
                    : null,
            ]));
            $rules['position'] = ['required', 'string', Rule::in(SchoolDirectory::positionsFor($user->role))];
        }

        if ($user->role === 'division') {
            $rules['office'] = ['nullable', 'string', 'max:255'];
            $rules['position'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if (($data['email'] ?? $user->email) !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->fill($data)->save();

        return back()->with('status', 'Account saved.');
    }
}
