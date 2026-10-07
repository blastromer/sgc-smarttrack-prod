<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SchoolDirectory;
use App\Support\SiteAppearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
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
        $user = request()->user();

        return Inertia::render('Docs', [
            'title' => 'Docs',
            'subtitle' => 'Role manuals, FAT rules, and walkthroughs',
            'role' => $user->role,
        ]);
    }

    public function help(): Response
    {
        $user = request()->user();

        return Inertia::render('Help', [
            'title' => 'Help',
            'subtitle' => 'Click-through walkthrough for '.$user->role_label,
            'role' => $user->role,
        ]);
    }

    public function configuration(): Response
    {
        return Inertia::render('settings/Configuration', [
            'title' => 'Configuration',
            'subtitle' => 'Color theme, font, and text size for SmartTrack',
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = SiteAppearance::sanitize($request->only(['theme', 'accent', 'font', 'text_size', 'density']));
        $publish = $request->boolean('publish') && $request->user()?->role === 'super';

        if ($publish) {
            SiteAppearance::publish($data);
        }

        $message = $publish
            ? 'Site default saved. Anyone without their own settings will see this theme.'
            : 'Appearance saved. It applies to every page you use in SmartTrack.';

        return back()
            ->with('status', $message)
            ->cookie(SiteAppearance::COOKIE, json_encode($data), 60 * 24 * 365, '/', null, false, false);
    }

    public function resetAppearance(Request $request): RedirectResponse
    {
        return back()
            ->with('status', 'Reset to the site default.')
            ->withCookie(Cookie::forget(SiteAppearance::COOKIE));
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
