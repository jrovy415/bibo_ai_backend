<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizMaterial extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quiz_id',
        'title',
        'type',
        'content',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
