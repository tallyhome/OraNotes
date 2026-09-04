<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    public const MAX_BYTES = 8_388_608; // 8 Mo

    public const MAX_PER_NOTE = 20;

    private const ALLOWED_MIMES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public function store(UploadedFile $file, User $user, Note $note): Attachment
    {
        if ($note->attachments()->count() >= self::MAX_PER_NOTE) {
            throw ValidationException::withMessages(['file' => 'Trop de pièces jointes sur cette note ('.self::MAX_PER_NOTE.' max).']);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Fichier trop volumineux (8 Mo max).']);
        }

        $mime = $file->getMimeType() ?: '';
        if (! isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages(['file' => 'Type de fichier non autorisé.']);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (! in_array($extension, self::ALLOWED_MIMES[$mime], true)) {
            throw ValidationException::withMessages(['file' => 'Extension incohérente avec le type réel.']);
        }

        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['file' => 'Image invalide.']);
        }

        $stored = $file->storeAs(
            'attachments/'.$user->id,
            (string) Str::uuid().'.'.$extension,
            'local'
        );

        return Attachment::query()->create([
            'user_id' => $user->id,
            'note_id' => $note->id,
            'disk' => 'local',
            'path' => $stored,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ]);
    }
}
