<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuestionType;

class QuestionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'multiple_choice', 'label' => 'Multiple Choice'],
            ['name' => 'true_false', 'label' => 'True/False'],
            ['name' => 'reading', 'label' => 'Reading']
        ];

        foreach ($types as $type) {
            QuestionType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
