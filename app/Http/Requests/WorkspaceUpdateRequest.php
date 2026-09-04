<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkspaceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Workspace $workspace */
        $workspace = $this->route('workspace');

        return $this->user()?->can('update', $workspace) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:32'],
            'color' => ['nullable', 'string', Rule::in(['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray'])],
            'is_default' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'canvas_settings' => ['sometimes', 'array'],
            'canvas_settings.zoom' => ['sometimes', 'numeric', 'min:0.2', 'max:4'],
            'canvas_settings.x' => ['sometimes', 'numeric'],
            'canvas_settings.y' => ['sometimes', 'numeric'],
            'canvas_settings.snap' => ['sometimes', 'boolean'],
        ];
    }
}
