<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasUuids;

    public $model_name = 'role_permission';

    protected $fillable = [
        'role_id',
        'permission_id',
    ];
}
