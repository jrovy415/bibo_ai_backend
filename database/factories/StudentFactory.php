<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Student;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition()
    {
        $nickname = $this->faker->unique()->firstName();

        return [
            'id'          => Str::uuid()->toString(),
            'nickname'    => $nickname,
            'grade_level' => $this->faker->randomElement(['Kinder', 'Grade 1']),
            'section'     => $this->faker->randomElement(['1', '2', '3', '4']),
            'slug'        => Str::slug($nickname . '-' . Str::random(5)),
        ];
    }
}
