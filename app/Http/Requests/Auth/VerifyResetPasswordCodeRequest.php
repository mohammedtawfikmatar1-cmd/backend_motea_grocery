<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class VerifyResetPasswordCodeRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
            ],
            'code' => [
                'required',
                'digits:'.$this->codeLength(),
            ],
        ];
    }

    /**
     * Get the configured verification code length.
     */
    private function codeLength(): int
    {
        return (int) config('verification.code_length');
    }
}
