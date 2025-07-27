<?php

namespace App\Models\Logs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'user_full_name',
        'action',
        'module',
        'payload',
        'result',
    ];

    public function scopeFilter(Builder $query): Builder
    {
        $search = request('search') ?? false;

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('module_name', 'LIKE', "%{$search}%")
                    ->orWhere('user_id', 'LIKE', "%{$search}%")
                    ->orWhere('user_full_name', 'LIKE', "%{$search}%")
                    ->orWhere('action', 'LIKE', "%{$search}%")
                    ->orWhere('id', 'LIKE', "%{$search}%");

                $q->orWhere(function ($subQuery) use ($search) {
                    $subQuery->whereRaw('LOWER(payload) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            });
        }

        return $query;
    }
}
