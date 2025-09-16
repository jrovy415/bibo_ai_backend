<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentDifficulty extends Model
{
    use HasFactory;

    protected $table = 'student_difficulties';

    protected $fillable = [
        'student_id',
        'difficulty',
    ];

    protected $primaryKey = 'student_id';
    public $incrementing = false; // because we don't use an id column
    protected $keyType = 'string'; // since PK includes uuid

    /**
     * Relationship: A difficulty belongs to a student.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
