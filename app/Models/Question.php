<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasUuids;

    public $model_name = 'Question';

    protected $fillable = [
        'question_type_id',
        'quiz_id',
        'question_text',
        'photo',
        'points',
    ];

    protected $with = ['choices', 'questionType'];

    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $query->where('question_text', 'LIKE', "%$search%");
        });
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questionType(): BelongsTo
    {
        return $this->belongsTo(QuestionType::class, 'question_type_id', 'id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(Choice::class);
    }
}
