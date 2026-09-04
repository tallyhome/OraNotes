<?php

namespace App\Http\Requests;

use App\Enums\NoteColor;
use App\Enums\NotePriority;
use App\Enums\NoteStatus;
use App\Http\Requests\Concerns\ValidatesOraDocument;
use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoteUpdateRequest extends FormRequest
{
    use ValidatesOraDocument;

    public function authorize(): bool
    {
        /** @var Note $note */
        $note = $this->route('note');

        return $this->user()?->can('update', $note) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:180'],
            'document' => ['sometimes', 'array'],
            'document.type' => ['required_with:document', 'in:doc'],
            'document.version' => ['required_with:document', 'integer'],
            'document.content' => ['required_with:document', 'array'],
            'html_preview' => ['nullable', 'string', 'max:100000'],
            'color' => ['sometimes', Rule::enum(NoteColor::class)],
            'icon' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', Rule::enum(NoteStatus::class)],
            'priority' => ['sometimes', Rule::enum(NotePriority::class)],
            'is_locked' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'x' => ['sometimes', 'numeric'],
            'y' => ['sometimes', 'numeric'],
            'width' => ['sometimes', 'numeric', 'min:140', 'max:1200'],
            'height' => ['sometimes', 'numeric', 'min:120', 'max:1200'],
            'rotation' => ['sometimes', 'numeric', 'min:-30', 'max:30'],
            'z_index' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($v) => $this->validateOraDocumentLimits($v));
    }
}
