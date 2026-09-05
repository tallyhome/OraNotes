<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Update\UpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public function __invoke(UpdateService $updates): Response
    {
        $status = $updates->status();
        $compat = $status['latest']
            ? $updates->compatibility($status['latest'])
            : ['ok' => true, 'errors' => []];

        return Inertia::render('Admin/Updates', [
            'status' => $status,
            'compatibility' => $compat,
            'repository' => config('oranotes.update.repository'),
        ]);
    }

    public function apply(Request $request, UpdateService $updates): RedirectResponse
    {
        $result = $updates->apply($request->user());

        return back()->with('status', $result['message']);
    }
}
