<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
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
        // PATCH /students/{id} — partial update (e.g. difficulty only)
        if ($this->isMethod('PATCH')) {
            return [
                'nickname'    => 'sometimes|string',
                'grade_level' => 'sometimes|in:Grade 1,Kinder',
                'section'     => 'sometimes|in:1,2,3,4',
                'difficulty'  => 'sometimes|string|in:Introduction,Easy,Medium,Hard,Expert,PostTest',
            ];
        }

        // POST /students/login — include grade_level and section
        // so the repository can update them on every login
        if ($this->is('*/students/login')) {
            return [
                'nickname'    => 'required|string',
                'grade_level' => 'required|in:Grade 1,Kinder',
                'section'     => 'required|in:1,2,3,4',
            ];
        }

        // POST /students — full creation
        return [
            'nickname'    => 'required|string',
            'grade_level' => 'required|in:Grade 1,Kinder',
            'section'     => 'required|in:1,2,3,4',
        ];
    }
}