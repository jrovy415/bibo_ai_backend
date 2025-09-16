<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\QuestionType;

class QuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Quiz fields
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'grade_level' => 'required|string',
            'difficulty' => 'required|in:Introduction,Easy,Medium,Hard',
            'time_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',

            // Questions
            'questions' => 'sometimes|array',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type_id' => 'required|exists:question_types,id',
            'questions.*.points' => 'required|integer|min:1',

            // Replace the current photo validation rule with:
            'questions.*.photo' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }

                    // Allow string filenames (after upload)
                    if (is_string($value)) {
                        return;
                    }

                    // Allow direct file upload
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }

                    // Otherwise fail
                    $fail("The {$attribute} must be a string filename, an uploaded file, or null.");
                },
            ],

            // Choices (only required if multiple choice)
            'questions.*.choices' => [
                'array',
                function ($attribute, $value, $fail) {
                    preg_match('/questions\.(\d+)\.choices/', $attribute, $matches);
                    $index = $matches[1] ?? null;

                    if ($index !== null) {
                        $questions = $this->input('questions', []);
                        if (!isset($questions[$index])) return;

                        $questionTypeId = $questions[$index]['question_type_id'] ?? null;
                        if (!$questionTypeId) return;

                        $questionType = QuestionType::find($questionTypeId);
                        if ($questionType && $questionType->name === 'multiple_choice') {
                            if (empty($value) || count($value) < 2) {
                                $fail('Each multiple-choice question must have at least 2 choices.');
                            }
                        }
                    }
                }
            ],

            'questions.*.choices.*.choice_text' => 'required_with:questions.*.choices|string',
            'questions.*.choices.*.is_correct' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The quiz title is required.',
            'difficulty.in' => 'Difficulty must be one of Introduction, Easy, Medium, or Hard.',
            'questions.*.question_text.required' => 'Each question must have text.',
            'questions.*.question_type_id.exists' => 'Invalid question type selected.',
            'questions.*.choices.*.choice_text.required_with' => 'Choice text is required for each choice.',
        ];
    }
}
