<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\User\App\Models\User;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique((new User)->getTable(), 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::defaults()],
            'terms' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ];
    }

    public function fullName(): string
    {
        return trim($this->string('name')->toString());
    }
}
