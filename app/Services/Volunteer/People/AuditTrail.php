<?php

namespace App\Services\Volunteer\People;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** سجلّ تدقيق لكلّ حركة على المرشّح وعلى طلب التسكين (13.4-د · 13.4-هـ). */
class AuditTrail
{
    public function record(?User $actor, string $action, Model $subject, array $old = [], array $new = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
