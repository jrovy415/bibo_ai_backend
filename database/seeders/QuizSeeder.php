<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\Choice;
use App\Models\User;
use App\Models\QuestionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Quiz::truncate();
        Question::truncate();
        Choice::truncate();

        Schema::enableForeignKeyConstraints();

        // Get admin teacher
        $teacher = User::where('username', 'admin')->first();
        if (!$teacher) {
            $this->command->error('No admin user found. Please create a user with username "admin".');
            return;
        }

        $gradeLevels = ['Kinder', 'Grade 1'];

        // Sentences for reading quizzes
        $questionsData = [
            'Kinder' => [
                'Introduction' => [
                    'I see a cat',
                    'The sun is up',
                    'Go to bed',
                    'I like milk',
                    'The dog runs'
                ],
                'Easy' => [
                    'I see a cat',
                    'The sun is hot',
                    'I like to run',
                    'The dog is big',
                    'My mom is kind'
                ],
                'Medium' => [
                    'I have a red ball',
                    'The bird can fly',
                    'I drink milk every day',
                    'The tree is tall',
                    'I read a book'
                ],
                'Hard' => [
                    'The elephant is very large',
                    'I like to eat honey and chocolate',
                    'The clock is on the wall',
                    'She drinks tea in the morning',
                    'The circle is blue'
                ],
            ],
            'Grade 1' => [
                'Introduction' => [
                    'I can run fast',
                    'See the blue sky',
                    'Open the book',
                    'I eat an apple',
                    'The cat jumps'
                ],
                'Easy' => [
                    'I can write my name',
                    'The dog runs fast',
                    'I like red apples',
                    'The sun is bright',
                    'I jump high'
                ],
                'Medium' => [
                    'My sister has a blue dress',
                    'I drink water every morning',
                    'The cat sleeps on the bed',
                    'We read books at school',
                    'The tree has green leaves'
                ],
                'Hard' => [
                    'The parrot is sitting on the branch',
                    'I use scissors to cut paper',
                    'Chocolate melts in the sun',
                    'The bus stops near my house',
                    'I see the moon and stars at night'
                ],
            ]
        ];

        // Get the "reading" question type
        $readingType = QuestionType::where('name', 'reading')->first();
        if (!$readingType) {
            $this->command->error('No "reading" question type found. Please seed QuestionType first.');
            return;
        }

        collect($gradeLevels)->each(function ($grade) use ($questionsData, $teacher, $readingType) {
            collect($questionsData[$grade])->each(function ($sentences, $difficulty) use ($grade, $teacher, $readingType) {
                $quiz = Quiz::create([
                    'teacher_id' => $teacher->id,
                    'title' => "$grade Reading Quiz - $difficulty",
                    'instructions' => 'Read each sentence aloud using your microphone.',
                    'grade_level' => $grade,
                    'difficulty' => $difficulty,
                    'time_limit' => $difficulty === 'Introduction' ? 5 : 10,
                    'is_active' => true,
                ]);

                collect($sentences)->each(function ($sentence) use ($quiz, $readingType) {
                    $question = Question::create([
                        'quiz_id' => $quiz->id,
                        'question_type_id' => $readingType->id,
                        'question_text' => $sentence,
                        'points' => 1,
                    ]);

                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $sentence,
                        'is_correct' => true,
                    ]);
                });
            });
        });

        $this->command->info('Reading quizzes with harder Introduction level seeded successfully!');
    }
}
