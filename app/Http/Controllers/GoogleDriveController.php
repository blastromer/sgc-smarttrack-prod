<?php

namespace App\Http\Controllers;

use App\Jobs\CreateSchoolDriveFolders;
use App\Models\User;
use App\Support\DriveMovFolders;
use App\Support\DriveWorkStatus;
use App\Support\GoogleDriveClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleDriveController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(DriveMovFolders::enabled(), 404);
        abort_unless($request->user()?->isSchoolStaff(), 403);

        $state = Str::random(40);
        Cache::put('google-oauth-state:'.$state, $request->user()->id, 600);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => DriveMovFolders::WRITE_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $home = rtrim((string) config('app.url'), '/').'/school/templates';

        if ($request->filled('error')) {
            return redirect($home)->with('status', 'Google Drive was not connected.');
        }

        $userId = Cache::pull('google-oauth-state:'.(string) $request->query('state'));
        $user = $userId ? User::query()->find($userId) : null;
        if (! $user?->isSchoolStaff()) {
            return redirect($home)->with('status', 'Google Drive sign-in expired. Open Connect Drive again.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => (string) $request->query('code'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        $refresh = $response->json('refresh_token');
        if (! $response->successful() || ! filled($refresh)) {
            return redirect($home)->with('status', 'Google Drive could not be connected. Try again.');
        }

        Cache::forever(DriveMovFolders::tokenCacheKey((int) $user->id), $refresh);
        Cache::forever(DriveMovFolders::scopeCacheKey((int) $user->id), DriveMovFolders::WRITE_SCOPE);

        return redirect($home)->with('status', 'Google Drive connected. Open a MOV folder to create this school directory, or use Create school folders.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSchoolStaff(), 403);
        Cache::forget(DriveMovFolders::tokenCacheKey((int) $request->user()->id));
        Cache::forget(DriveMovFolders::scopeCacheKey((int) $request->user()->id));

        return back()->with('status', 'Google Drive disconnected.');
    }

    public function open(Request $request, string $key): RedirectResponse
    {
        $folder = DriveMovFolders::find($key);
        abort_unless($folder, 404);

        $user = $request->user();
        abort_unless($user?->isSchoolStaff(), 403);

        $parent = DriveMovFolders::copyUrl($folder['id']);
        $placement = DriveMovFolders::placement($user);
        if ($placement) {
            $cached = Cache::get(DriveMovFolders::schoolFolderCacheKey(
                $user->packetSchoolCode(),
                $placement['year'],
                $placement['school'],
                $folder['id'],
            ));
            if (is_string($cached) && $cached !== '') {
                return redirect()->away('https://drive.google.com/drive/folders/'.$cached);
            }
        }

        if (! DriveMovFolders::connected($user->id)) {
            return redirect()->away($parent);
        }

        if (! $placement) {
            return redirect()
                ->route('school.templates')
                ->with('status', 'Add the school name on Form data first. Folders are created as school year / school name.');
        }

        $result = app(GoogleDriveClient::class)->ensure(
            (string) Cache::get(DriveMovFolders::tokenCacheKey($user->id)),
            $user->packetSchoolCode(),
            $folder['id'],
            $placement['year'],
            $placement['school'],
        );

        if ($result['error'] === 'scope') {
            Cache::forget(DriveMovFolders::scopeCacheKey($user->id));
        }

        if (! $result['id']) {
            return redirect()->away($parent);
        }

        return redirect()->away('https://drive.google.com/drive/folders/'.$result['id']);
    }

    public function createFolders(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isSchoolStaff(), 403);

        if (! DriveMovFolders::writable($user->id)) {
            return back()->with('status', 'Reconnect Drive so SmartTrack can create school folders.');
        }

        $placement = DriveMovFolders::placement($user);
        if (! $placement) {
            return back()->with('status', 'Add the school name on Form data first. Folders are created as school year / school name inside each MOV folder.');
        }

        $work = DriveWorkStatus::claim(
            CreateSchoolDriveFolders::cacheKey($user->packetSchoolCode()),
            'Waiting for the worker.',
            fn () => CreateSchoolDriveFolders::dispatch($user->id, $user->packetSchoolCode()),
            'queued',
        );

        return back()
            ->with('status', $work['message'])
            ->with('drive_work', [
                'state' => $work['state'],
                'message' => $work['message'],
                'done' => $work['done'],
                'total' => $work['total'],
            ])
            ->with('drive_more', $work['more']);
    }
}
