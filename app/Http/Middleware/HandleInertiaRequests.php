<?php

namespace App\Http\Middleware;

use App\Models\Assessment;
use App\Models\Mov;
use App\Models\User;
use App\Support\AssessmentEngine;
use App\Support\SgcAi;
use App\Support\SgcSample;
use App\Support\SiteAppearance;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
                'drive_more' => (bool) $request->session()->get('drive_more'),
                'drive_work' => $request->session()->get('drive_work'),
            ],
            'sgc' => $user ? [
                'nav' => SgcSample::nav($user->role),
                'counts' => $this->navCounts($user),
                'notices' => $this->noticesFor($user),
                'noticeHref' => SgcSample::noticeHref($user->role),
                'flow' => $user->isSchoolStaff() ? AssessmentEngine::flowFor($user) : null,
                'ai' => SgcAi::forUser($user, $request->path()),
                'appearance' => [
                    'current' => SiteAppearance::resolved(SiteAppearance::forRequest($request)),
                    'site' => SiteAppearance::resolved(SiteAppearance::site()),
                    'can_publish' => $user->role === 'super',
                ],
            ] : null,
        ]);
    }

    /**
     * Sidebar counters for the Division Admin. Other roles get none.
     *
     * @return array<string, int>
     */
    private function navCounts(User $user): array
    {
        if ($user->role !== 'division') {
            return [];
        }

        return [
            '/division/queue' => Assessment::query()->whereIn('status', ['submitted', 'under_review'])->count(),
            '/division/alerts' => Mov::query()->where('status', 'returned')->count(),
            '/division/registrations' => User::query()->where('role', 'school_head')->where('status', 'pending')->count(),
        ];
    }

    /**
     * @return array<int, array{id: string, unread: bool, title: string, detail: mixed, when: string, href: mixed}>
     */
    private function noticesFor(User $user): array
    {
        $live = $user->notifications()
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'unread' => $notification->read_at === null,
                'title' => $notification->data['title'] ?? 'Notice',
                'detail' => $notification->data['detail'] ?? '',
                'when' => $notification->created_at?->diffForHumans() ?? '',
                'href' => $notification->data['href'] ?? SgcSample::noticeHref($user->role),
            ])
            ->all();

        return $live;
    }
}
