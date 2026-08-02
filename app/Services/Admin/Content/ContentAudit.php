<?php

namespace App\Services\Admin\Content;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;

/**
 * سجلّ التدقيق لمجال المحتوى (12.4-هـ · 12.5-د): مَن عدّل ماذا ومتى.
 *
 * لماذا خدمة واحدة؟ لأنّ الإصدار والإلغاء وتعديل التدريب كلّها تكتب في نفس
 * السجلّ الذي تقرأه شاشة «سجلّ التدقيق» — فلو كتب كلّ شاشة بطريقتها اختلف العرض.
 */
class ContentAudit
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(Model $subject, string $action, array $old = [], array $new = [], ?User $actor = null): ?AuditLog
    {
        if (! setting('admin_content.audit.enabled', true)) {
            return null;
        }

        return AuditLog::create([
            'user_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $this->trim($old),
            'new_values' => $this->trim($new),
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }

    /** آخر ما جرى على سجلّ بعينه — يُعرَض في بانل «سجلّ التدقيق». */
    public function forSubject(Model $subject, ?int $limit = null): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->where('auditable_type', $subject->getMorphClass())
            ->where('auditable_id', $subject->getKey())
            ->latest('id')
            ->limit($limit ?? (int) setting('admin_content.audit.page_size', 20))
            ->get();
    }

    /**
     * الفروق فقط — فلا يمتلئ السجلّ بقيم لم تتغيّر.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $old, array $new): array
    {
        $changed = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] != $value) {
                $changed[$key] = $value;
            }
        }

        return [array_intersect_key($old, $changed), $changed];
    }

    /** @param  array<string, mixed>  $values */
    private function trim(array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        $max = (int) setting('admin_content.audit.max_value_length', 500);

        return collect($values)
            ->map(fn ($value) => is_scalar($value) || $value === null
                ? (is_string($value) ? mb_substr($value, 0, $max) : $value)
                : mb_substr(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '', 0, $max))
            ->all();
    }
}
