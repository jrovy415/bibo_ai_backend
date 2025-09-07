<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Choice extends Model
{
    use HasUuids;

    public $model_name = 'Choice';

    protected $fillable = [
        'question_id',
        'choice_text',
        'is_correct',
    ];

    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $query->where('choice_text', 'LIKE', "%$search%");
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
