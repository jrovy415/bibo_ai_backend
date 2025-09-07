<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Question;
use App\Models\QuestionType;

class AnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        // Always required
        $rules = [
            'attempt_id' => ['required', 'uuid', 'exists:quiz_attempts,id'],
            'question_id' => ['required', 'uuid', 'exists:questions,id'],
            'choice_id'  => ['nullable', 'uuid', 'exists:choices,id'], // default nullable
            'transcript' => ['nullable', 'string']
        ];

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->sometimes('choice_id', 'required|uuid|exists:choices,id', function ($input) {
            $question = Question::find($input->question_id);
            if (!$question) return false;

            $questionType = QuestionType::find($question->question_type_id);

            return $questionType->name !== 'reading';
        });

        $validator->sometimes('transcript', 'required|string', function ($input) {
            $question = Question::find($input->question_id);

            if (!$question) return false;

            $questionType = QuestionType::find($question->question_type_id);

            return $questionType->name === 'reading';
        });
    }

    public function messages(): array
    {
        return [
            'attempt_id.required' => 'The quiz attempt ID is required.',
            'attempt_id.uuid'     => 'The quiz attempt ID must be a valid UUID.',
            'attempt_id.exists'   => 'The quiz attempt does not exist.',

            'question_id.required'     => 'The question ID is required.',
            'question_id.uuid'         => 'The question ID must be a valid UUID.',
            'question_id.exists'       => 'The question does not exist.',

            'choice_id.required'       => 'The choice ID is required for this question.',
            'choice_id.uuid'           => 'The choice ID must be a valid UUID.',
            'choice_id.exists'         => 'The choice does not exist.',
        ];
    }
}
