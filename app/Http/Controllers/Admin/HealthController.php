<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\HealthService;
use Inertia\Inertia;
use Inertia\Response;

class HealthController extends Controller
{
    public function __invoke(HealthService $health): Response
    {
        return Inertia::render('Admin/Health', $health->summary());
    }
}
