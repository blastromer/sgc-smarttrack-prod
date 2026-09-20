<?php

namespace App\Http\Controllers;

use App\Support\PortalMetrics;
use App\Support\SgcSample;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function superOverview(): Response
    {
        return Inertia::render('super/Overview', PortalMetrics::superOverview());
    }

    public function superDivisions(): Response
    {
        return Inertia::render('super/Divisions', PortalMetrics::superDivisions());
    }

    public function superCycles(): Response
    {
        return Inertia::render('super/Cycles', PortalMetrics::superCycles());
    }

    public function divisionOverview(Request $request): Response
    {
        return Inertia::render('division/Overview', PortalMetrics::divisionOverview($request->user()));
    }

    public function divisionQueue(): Response
    {
        return Inertia::render('division/Queue', SgcSample::page('division.queue'));
    }

    public function divisionSchools(): Response
    {
        return Inertia::render('division/Schools', PortalMetrics::divisionSchools());
    }

    public function divisionAlerts(): Response
    {
        return Inertia::render('division/Alerts', PortalMetrics::divisionAlerts());
    }

    public function schoolDashboard(): Response
    {
        return Inertia::render('school/Dashboard', SgcSample::page('school.dashboard'));
    }

    public function schoolAssessment(): Response
    {
        return Inertia::render('school/Assessment', SgcSample::page('school.assessment'));
    }

    public function schoolMovs(): Response
    {
        return Inertia::render('school/Movs', SgcSample::page('school.movs'));
    }

    public function schoolSubmit(): Response
    {
        return Inertia::render('school/Submit', SgcSample::page('school.submit'));
    }

    public function schoolNotifications(): Response
    {
        return Inertia::render('school/Notifications', SgcSample::page('school.notifications'));
    }
}
