<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    public $model_name = 'User';

    protected $fillable = [
        'role_id',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'gender',
        'birthday',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'password' => 'hashed',
    ];

    protected $appends = ['initials'];

    public function getInitialsAttribute(): string
    {
        $firstInitial = $this->first_name[0] ?? '';
        $lastInitial = $this->last_name[0] ?? '';
        return strtoupper($firstInitial . $lastInitial);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function scopeFilter($query)
    {
        $search = request('search');

        $query->when($search, function ($query) use ($search) {
            $columns = [
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'gender',
                'birthday',
            ];

            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'LIKE', "%$search%");
                }
            });
        });
    }
}
