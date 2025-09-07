<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasUuids;

    public $model_name = 'Answer';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'choice_id',
        'choice_string',
        'is_correct',
    ];

    protected $with = ['question', 'choice'];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // Scope for search/filter
    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $query->whereHas('question', function ($q) use ($search) {
                $q->where('question_text', 'LIKE', "%$search%");
            });
        });
    }

    // Relationships
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(Choice::class);
    }

    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->quizAttempt?->student();
    }
}
