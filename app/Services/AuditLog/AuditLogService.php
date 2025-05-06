<?php

namespace App\Services\AuditLog;

use App\Models\Logs\AuditLog;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogService implements AuditLogServiceInterface
{
    public function insertLog($model, $action, $attr = [])
    {
        DB::beginTransaction();

        try {
            if (Auth::guest()) {
                throw new Exception("Invalid Audit Log, Not authenticated.");
            }
            $user = Auth::user();

            $data = json_encode($attr, JSON_THROW_ON_ERROR);

            AuditLog::create([
                'module_name' => get_class($model),
                'user_id' => $user->id,
                'user_full_name' => $user->firstname . ' ' . $user->lastname,
                'action' => $action,
                'payload' => $data,
                'result' => 'Success'
            ]);

            DB::commit();
        } catch (Exception $e) {
            logger()->critical('AuditLogService ' . $e->getMessage());

            DB::rollBack();
        }
    }

    public function loginLog($action, $attr)
    {
        DB::beginTransaction();

        try {
            $user = User::where('email', $attr['email'])->first();

            $data = json_encode($attr, JSON_THROW_ON_ERROR);

            AuditLog::create([
                'user_id' => $user->id,
                'user_full_name' => $user->firstname . ' ' . $user->lastname,
                'action' => $action,
                'payload' => $data,
                'result' => 'Success'
            ]);

            DB::commit();
        } catch (Exception $e) {
            logger()->critical('AuditLogService ' . $e->getMessage());

            DB::rollBack();
        }
    }

    public function getLogsByDate(string $from, string $to)
    {
        try {
            $limit = request()->input('limit', 10);

            $logs = AuditLog::whereBetween('created_at', [$from, $to])
                ->filter()
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            return $logs;
        } catch (Exception $e) {
            logger()->critical('AuditLogService ' . $e->getMessage());
        }
    }
}
