<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizFeedback extends Model
{
    use HasUuids;

    protected $table = 'quiz_feedbacks'; // explicitly set table name

    protected $fillable = [
        'student_id',
        'quiz_id',
        'quiz_attempt_id',
        'feeling',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}