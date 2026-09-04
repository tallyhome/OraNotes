<?php

namespace App\Http\Requests;

use App\Enums\NoteColor;
use App\Enums\NotePriority;
use App\Enums\NoteStatus;
use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Workspace|null $workspace */
        $workspace = $this->route('workspace');

        return $workspace
            ? ($this->user()?->can('create', [Note::class, $workspace]) ?? false)
            : (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:180'],
            'document' => ['nullable', 'array'],
            'document.type' => ['required_with:document', 'in:doc'],
            'document.version' => ['required_with:document', 'integer'],
            'document.content' => ['required_with:document', 'array'],
            'html_preview' => ['nullable', 'string'],
            'color' => ['nullable', Rule::enum(NoteColor::class)],
            'icon' => ['nullable', 'string', 'max:32'],
            'x' => ['nullable', 'numeric'],
            'y' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric', 'min:140', 'max:1200'],
            'height' => ['nullable', 'numeric', 'min:120', 'max:1200'],
            'status' => ['nullable', Rule::enum(NoteStatus::class)],
            'priority' => ['nullable', Rule::enum(NotePriority::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:40'],
            'template' => ['nullable', 'string', Rule::in(['blank', 'todo', 'meeting'])],
        ];
    }
}
