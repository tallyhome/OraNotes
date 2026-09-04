<?php

namespace App\Http\Requests;

use App\Enums\SharePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'permission' => ['required', Rule::enum(SharePermission::class)],
        ];
    }
}
