<?php

namespace App\Http\Controllers;

use App\Support\SgcSample;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function superOverview(): Response
    {
        return Inertia::render('super/Overview', SgcSample::page('super.overview'));
    }

    public function superDivisions(): Response
    {
        return Inertia::render('super/Divisions', SgcSample::page('super.divisions'));
    }

    public function superCycles(): Response
    {
        return Inertia::render('super/Cycles', SgcSample::page('super.cycles'));
    }

    public function divisionOverview(): Response
    {
        return Inertia::render('division/Overview', SgcSample::page('division.overview'));
    }

    public function divisionQueue(): Response
    {
        return Inertia::render('division/Queue', SgcSample::page('division.queue'));
    }

    public function divisionSchools(): Response
    {
        return Inertia::render('division/Schools', SgcSample::page('division.schools'));
    }

    public function divisionAlerts(): Response
    {
        return Inertia::render('division/Alerts', SgcSample::page('division.alerts'));
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
