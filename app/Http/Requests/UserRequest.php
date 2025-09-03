<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'role_id' => 'required|exists:roles,id',
            'username' => 'required|string|unique:users,username,' . $this->route('id'),
            'first_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'last_name' => 'required|string',
            // 'email' => 'required|email',
            // 'gender' => 'required|in:Male,Female',
            // 'birthday' => 'required|date|date_format:Y-m-d',
        ];
    }
}
