<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkspaceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Workspace::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:32'],
            'color' => ['nullable', 'string', Rule::in(['yellow', 'blue', 'green', 'pink', 'purple', 'orange', 'gray'])],
            'is_default' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
        ];
    }
}
