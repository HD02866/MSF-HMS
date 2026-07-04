<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'table_name' => $model?->getTable(),
            'record_id' => $model?->getKey(),
            'old_value' => $oldValues,
            'new_value' => $newValues,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
