<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class NotePositionsRequest extends FormRequest
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
            'positions' => ['required', 'array', 'max:200'],
            'positions.*.id' => ['required_without:positions.*.uuid', 'string', 'uuid'],
            'positions.*.uuid' => ['required_without:positions.*.id', 'string', 'uuid'],
            'positions.*.x' => ['required', 'numeric'],
            'positions.*.y' => ['required', 'numeric'],
            'positions.*.width' => ['sometimes', 'numeric', 'min:140', 'max:1200'],
            'positions.*.height' => ['sometimes', 'numeric', 'min:120', 'max:1200'],
            'positions.*.z_index' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'positions.*.rotation' => ['sometimes', 'numeric', 'min:-30', 'max:30'],
        ];
    }
}
