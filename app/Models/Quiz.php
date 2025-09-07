<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasUuids;

    public $model_name = 'Quiz';

    protected $fillable = [
        'teacher_id',
        'title',
        'instructions',
        'grade_level',
        'difficulty',
        'time_limit',
        'is_active',
    ];

    protected $with = ['teacher', 'questions.choices'];

    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $columns = [
                'title',
                'instructions',
                'grade_level',
                'difficulty',
            ];

            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'LIKE', "%$search%");
                }
            });
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function quizAttempt(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
