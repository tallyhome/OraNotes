<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    public function __invoke(): Response
    {
        $attachments = Attachment::query()->with('user')->latest('id')->paginate(20);

        return Inertia::render('Admin/Storage', [
            'total_bytes' => (int) Attachment::query()->sum('size'),
            'count' => Attachment::query()->count(),
            'attachments' => $attachments->through(fn (Attachment $a) => [
                'id' => $a->uuid,
                'name' => $a->original_name,
                'mime' => $a->mime,
                'size' => $a->size,
                'user' => $a->user?->email,
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
