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

        // -------------------------------
        // 1. Sentences for reading quizzes
        // -------------------------------
        $questionsData = [
            'Kinder' => [
                'Introduction' => [
                    'I see a cat',
                    'The sun is up',
                    'Go to bed',
                    'I like milk',
                    'The dog runs',
                    'It is hot',
                    'I jump high',
                    'The boy is sad',
                    'I see mom',
                    'We play ball',
                ],
                'Easy' => [
                    'I see a big red cat',
                    'The bright sun is hot',
                    'I like to run at school',
                    'The brown dog is very big',
                    'My kind mom cooks rice',
                    'I have a small red hat',
                    'The yellow bird can sing loud',
                    'We go to the big school',
                    'I eat white rice with fish',
                    'The blue ball is round',
                ],
                'Medium' => [
                    'I have a shiny red ball',
                    'The little bird can fly high',
                    'I drink cold milk every day',
                    'The tall tree has green leaves',
                    'I read a short story book',
                    'My dad drives a blue car',
                    'The stars are bright at night',
                    'She has a pretty pink doll',
                    'We like to draw and paint',
                    'The small fish can swim fast',
                ],
                'Hard' => [
                    'The elephant is very large',
                    'I like to eat honey and chocolate',
                    'The old clock is on the wall',
                    'She drinks tea in the early morning',
                    'The big circle is painted blue',
                    'The rabbit hops quickly in the field',
                    'My sister sings loudly in the hall',
                    'I put my shoes under the bed',
                    'We travel far away by airplane',
                    'The candle melts slowly in the heat',
                ],
            ],
            'Grade 1' => [
                'Introduction' => [
                    'I can run fast',
                    'See the blue sky',
                    'Open the book',
                    'I eat an apple',
                    'The cat jumps',
                    'I like my toy car',
                    'She has a red bag',
                    'The dog barks loud',
                    'We sit on the mat',
                    'The boy claps hands',
                ],
                'Easy' => [
                    'I can write my full name',
                    'The black dog runs very fast',
                    'I like to eat red apples',
                    'The bright sun shines in the sky',
                    'I jump high over the rope',
                    'The small cup is full of milk',
                    'She plays outside with her friend',
                    'We go to class every morning',
                    'I eat soft bread and butter',
                    'The kite flies high in the sky',
                ],
                'Medium' => [
                    'My sister has a shiny blue dress',
                    'I drink clean water every morning',
                    'The black cat sleeps on the bed',
                    'We read many books at school',
                    'The big tree has green leaves',
                    'I wash my hands before eating food',
                    'He kicks the round soccer ball',
                    'The wooden chair is very strong',
                    'Birds sing early in the morning',
                    'The wall clock shows the correct time',
                ],
                'Hard' => [
                    'The green parrot is sitting on the branch',
                    'I use sharp scissors to cut colored paper',
                    'Sweet chocolate melts quickly in the hot sun',
                    'The yellow bus stops near my small house',
                    'I see the bright moon and shining stars at night',
                    'The teacher writes neatly on the blackboard',
                    'We celebrate birthdays happily with chocolate cake',
                    'A farmer plants rice in the muddy field',
                    'The modern computer is on the wooden table',
                    'The long river flows calmly to the wide sea',
                ],
            ]
        ];

        // -------------------------------
        // 2. Activity-based reading quizzes
        // -------------------------------
        $activities = [
            'Kinder' => [
                'Easy' => 'Match simple words (e.g., sun, dog, cat) with their corresponding pictures.',
                'Medium' => 'Listen to a short 3–4 sentence story and answer simple “Who/What” questions.',
                'Hard' => 'Arrange 3–4 picture cards in sequence to show the beginning, middle, and end of a story.',
            ],
            'Grade 1' => [
                'Easy' => 'Read simple CVC words (cat, dog, sun) and connect them to pictures.',
                'Medium' => 'Read 2–3 short sentences (e.g., “The dog runs. The sun is hot.”) and answer yes/no questions.',
                'Hard' => 'Read a short paragraph (4–5 sentences) and answer “Who, What, Where” comprehension questions.',
            ],
        ];

        // Get the "reading" question type
        $readingType = QuestionType::where('name', 'reading')->first();
        if (!$readingType) {
            $this->command->error('No "reading" question type found. Please seed QuestionType first.');
            return;
        }

        // Seed sentence-based quizzes
        collect($gradeLevels)->each(function ($grade) use ($questionsData, $teacher, $readingType) {
            collect($questionsData[$grade])->each(function ($sentences, $difficulty) use ($grade, $teacher, $readingType) {
                $quiz = Quiz::create([
                    'teacher_id'   => $teacher->id,
                    'title'        => "$grade Reading Quiz - $difficulty",
                    'instructions' => 'Read each sentence aloud using your microphone.',
                    'grade_level'  => $grade,
                    'difficulty'   => $difficulty,
                    'time_limit'   => $difficulty === 'Introduction' ? 5 : 10,
                    'is_active'    => true,
                ]);

                collect($sentences)->each(function ($sentence) use ($quiz, $readingType) {
                    $question = Question::create([
                        'quiz_id'          => $quiz->id,
                        'question_type_id' => $readingType->id,
                        'question_text'    => $sentence,
                        'points'           => 1,
                    ]);

                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $sentence,
                        'is_correct'  => true,
                    ]);
                });
            });
        });

        // Seed activity-based quizzes
        collect($activities)->each(function ($levels, $grade) use ($teacher, $readingType) {
            collect($levels)->each(function ($description, $difficulty) use ($grade, $teacher, $readingType) {
                $quiz = Quiz::create([
                    'teacher_id'   => $teacher->id,
                    'title'        => "$grade Reading Activity - $difficulty",
                    'instructions' => 'Follow the activity and answer accordingly.',
                    'grade_level'  => $grade,
                    'difficulty'   => $difficulty,
                    'time_limit'   => 10,
                    'is_active'    => true,
                ]);

                $question = Question::create([
                    'quiz_id'          => $quiz->id,
                    'question_type_id' => $readingType->id,
                    'question_text'    => $description,
                    'points'           => 1,
                ]);

                Choice::create([
                    'question_id' => $question->id,
                    'choice_text' => $description,
                    'is_correct'  => true,
                ]);
            });
        });

        $this->command->info('Reading quizzes (sentences + activities + intro) seeded successfully!');
    }
}
