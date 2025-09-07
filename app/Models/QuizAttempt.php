<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasUuids;

    public $model_name = 'Quiz Attempt';

    protected $fillable = [
        'student_id',
        'quiz_id',
        'score',
        'started_at',
        'completed_at',
    ];

    protected $with = ['student', 'quiz', 'answers'];

    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $query->whereHas('quiz', function ($q) use ($search) {
                $q->where('title', 'LIKE', "%$search%");
            });
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'attempt_id', 'id');
    }
}
