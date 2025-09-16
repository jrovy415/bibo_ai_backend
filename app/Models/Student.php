<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model
{
    use HasFactory, HasUuids, HasApiTokens;

    public $model_name = 'Student';

    protected $fillable = [
        'nickname',
        'grade_level',
        'section',
        'slug',
    ];

    // protected $with = ['currentDificulty'];

    protected static function booted()
    {
        static::creating(function ($student) {
            if (empty($student->slug) && !empty($student->nickname)) {
                $student->slug = Str::slug($student->nickname);
            }
        });

        static::updating(function ($student) {
            if ($student->isDirty('nickname')) {
                $student->slug = Str::slug($student->nickname);
            }
        });
    }

    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $query->where('nickname', 'LIKE', "%$search%");
        });
    }

    /**
     * A student’s current/active difficulty (latest one).
     */
    public function currentDifficulty(): HasOne
    {
        return $this->hasOne(StudentDifficulty::class, 'student_id', 'id');
    }
}
