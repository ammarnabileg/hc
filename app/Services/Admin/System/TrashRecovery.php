<?php

namespace App\Services\Admin\System;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Entity;
use App\Models\LearningPath;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use App\Models\VideoComment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * سلّة المحذوفات الموحّدة (`soft_delete_recovery` — 12.2.2، سطر 2188-2191).
 *
 * أربعة أفعال منصوصة بالحرف:
 *  - `.view`    عرض عنصر محذوف مبدئيًّا وبياناته قبل الاسترجاع.
 *  - `.list`    استعراض سلّة المحذوفات **عبر الموارد** — لا شاشة لكلّ مورد.
 *  - `.restore` استرجاع **داخل النافذة الزمنيّة** (مهلة الاحتفاظ) وحدها.
 *  - `.delete`  الحذف النهائيّ الذي لا رجعة فيه — بتأكيد وتوثيق (🔒).
 *
 * والموارد السبعة التي تحمل `SoftDeletes` فعليًّا في المشروع اليوم — تحقّقٌ
 * مباشر بالبحث لا افتراض: `Course · Entity · LearningPath · Product · Task ·
 * User · VideoComment`. أيّ موديل يضيف `SoftDeletes` لاحقًا يُضاف هنا وحده
 * ليظهر في السلّة الموحّدة — لا شاشة جديدة لكلّ مورد.
 *
 * ⚠️ **«مَن حذفه» أفضل جهدٍ ممكن لا يقين**: لا عمود `deleted_by` على أيٍّ من
 * الجداول السبعة اليوم، ومسارات الحذف نفسها تكتب فى `audit_logs` بأسلوبٍ غير
 * موحَّد (بعضها يسجّل الموديل نفسه، وبعضها — كحذف مهمّة من داخل هدف تطوّعيّ —
 * يسجّل الهدفَ الأب لا المهمّة). فنبحث عن أقرب صفّ تدقيقٍ لنفس النوع والمعرّف
 * خلال نافذة زمنيّة ضيّقة حول لحظة الحذف، ونعرض **«—» بصراحة** حين لا نجد
 * شيئًا — لا نخترع اسمًا.
 */
class TrashRecovery
{
    /** @var array<string, class-string<Model>> */
    public const RESOURCES = [
        'courses' => Course::class,
        'entities' => Entity::class,
        'learning_paths' => LearningPath::class,
        'products' => Product::class,
        'tasks' => Task::class,
        'users' => User::class,
        'video_comments' => VideoComment::class,
    ];

    /** أقصى عدد صفوف يُقرَأ من كلّ موديل قبل الدمج والترتيب — يكفي لسلّة إداريّة (لا سجلّ تاريخيّ ضخم) */
    private const PER_MODEL_CAP = 500;

    /** نافذة البحث في الأوديت حول لحظة الحذف — دقائق قبلها وبعدها */
    private const AUDIT_MATCH_MINUTES = 5;

    /** @return array<string, string> */
    public function resourceLabels(): array
    {
        return [
            'courses' => (string) setting('admin.trash.resource_courses', 'تدريب'),
            'entities' => (string) setting('admin.trash.resource_entities', 'كيان'),
            'learning_paths' => (string) setting('admin.trash.resource_learning_paths', 'مسار تدريبيّ'),
            'products' => (string) setting('admin.trash.resource_products', 'منتج متجر'),
            'tasks' => (string) setting('admin.trash.resource_tasks', 'مهمّة تطوّع'),
            'users' => (string) setting('admin.trash.resource_users', 'مستخدم'),
            'video_comments' => (string) setting('admin.trash.resource_video_comments', 'تعليق فيديو'),
        ];
    }

    /** مهلة الاحتفاظ بالمحذوف قبل الحذف النهائيّ — إعدادٌ لا رقمٌ محروق (2.13 · «Soft-delete = 30 يومًا» سطر 5169) */
    public function retentionDays(): int
    {
        return max(1, (int) setting('admin.trash.retention_days', 30));
    }

    /**
     * قائمة سلّة المحذوفات — مدمجة عبر الموارد ومرتّبة بأحدث حذفٍ أوّلًا،
     * مع ترقيمٍ يدويّ لأنّ المصدر مجموعة استعلامات على جداول مختلفة لا استعلامًا واحدًا.
     */
    public function list(?string $type, int $perPage, int $page): LengthAwarePaginator
    {
        $types = ($type !== null && array_key_exists($type, self::RESOURCES)) ? [$type] : array_keys(self::RESOURCES);

        $rows = collect();

        foreach ($types as $key) {
            /** @var class-string<Model> $model */
            $model = self::RESOURCES[$key];

            $model::query()->onlyTrashed()
                ->orderByDesc('deleted_at')
                ->limit(self::PER_MODEL_CAP)
                ->get()
                ->each(function (Model $row) use (&$rows, $key) {
                    $rows->push($this->present($key, $row));
                });
        }

        $sorted = $rows->sortByDesc('deleted_at_ts')->values();
        $total = $sorted->count();
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /** إحصاء سريع للكروت العلويّة — على نفس بيانات `list()` بلا تكرار منطقٍ منفصل */
    public function counts(): array
    {
        $all = collect(array_keys(self::RESOURCES))
            ->flatMap(fn ($key) => self::RESOURCES[$key]::query()->onlyTrashed()->limit(self::PER_MODEL_CAP)->get()
                ->map(fn (Model $row) => $this->present($key, $row)));

        return [
            'total' => $all->count(),
            'expiring_soon' => $all->filter(fn ($row) => $row['remaining_days'] > 0 && $row['remaining_days'] <= 3)->count(),
            'window_closed' => $all->filter(fn ($row) => $row['remaining_days'] <= 0)->count(),
        ];
    }

    /** بيانات عنصرٍ محذوف بالتفصيل — لبوب-أب «عرض قبل الاسترجاع» (`.view`) */
    public function find(string $type, int|string $id): ?array
    {
        if (! array_key_exists($type, self::RESOURCES)) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = self::RESOURCES[$type];
        $row = $model::query()->onlyTrashed()->find($id);

        if (! $row) {
            return null;
        }

        return $this->present($type, $row) + ['attributes' => $row->toArray()];
    }

    /**
     * استرجاع — فقط **داخل النافذة الزمنيّة** (شرط `.restore` المنصوص حرفيًّا).
     *
     * @return array{ok: bool, reason?: string}
     */
    public function restore(string $type, int|string $id, ?User $actor): array
    {
        if (! array_key_exists($type, self::RESOURCES)) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        /** @var class-string<Model> $model */
        $model = self::RESOURCES[$type];
        $row = $model::query()->onlyTrashed()->find($id);

        if (! $row) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        if ($this->remainingDays($row->deleted_at) <= 0) {
            return ['ok' => false, 'reason' => 'window_closed'];
        }

        $title = $this->titleOf($type, $row);
        $row->restore();

        $this->audit($actor, 'soft_delete_recovery.restore', $model, $row->getKey(), [], ['type' => $type, 'title' => $title]);

        return ['ok' => true];
    }

    /** حذف نهائيّ لا رجعة فيه (`.delete` 🔒) — التأكيد يبقى في الواجهة، والتوثيق هنا إلزاميّ */
    public function forceDelete(string $type, int|string $id, ?User $actor): bool
    {
        if (! array_key_exists($type, self::RESOURCES)) {
            return false;
        }

        /** @var class-string<Model> $model */
        $model = self::RESOURCES[$type];
        $row = $model::query()->onlyTrashed()->find($id);

        if (! $row) {
            return false;
        }

        $title = $this->titleOf($type, $row);
        $row->forceDelete();

        $this->audit($actor, 'soft_delete_recovery.delete', $model, $id, ['type' => $type, 'title' => $title], []);

        return true;
    }

    /** أيّام متبقّية قبل الحذف النهائيّ — 0 يعني النافذة أُغلِقت (لا يُرفَض العرض، يُرفَض الاسترجاع وحده) */
    public function remainingDays(?CarbonInterface $deletedAt): int
    {
        if ($deletedAt === null) {
            return 0;
        }

        $deadline = $deletedAt->copy()->addDays($this->retentionDays());
        $remainingSeconds = $deadline->getTimestamp() - now()->getTimestamp();

        return (int) max(0, ceil($remainingSeconds / 86400));
    }

    /** @return array<string, mixed> */
    private function present(string $type, Model $row): array
    {
        $deletedAt = $row->deleted_at;

        return [
            'type' => $type,
            'id' => $row->getKey(),
            'title' => $this->titleOf($type, $row),
            'deleted_at' => $deletedAt,
            'deleted_at_ts' => $deletedAt?->getTimestamp() ?? 0,
            'deleted_by' => $this->deletedByOf($type, $row),
            'remaining_days' => $this->remainingDays($deletedAt),
        ];
    }

    private function titleOf(string $type, Model $row): string
    {
        return match ($type) {
            'courses', 'entities', 'learning_paths', 'products' => (string) ($row->getAttribute('name_ar') ?: '#'.$row->getKey()),
            'tasks' => (string) ($row->getAttribute('title') ?: '#'.$row->getKey()),
            'users' => (string) ($row->getAttribute('name') ?: $row->getAttribute('email') ?: '#'.$row->getKey()),
            'video_comments' => Str::limit((string) $row->getAttribute('body'), 60) ?: '#'.$row->getKey(),
            default => '#'.$row->getKey(),
        };
    }

    /** أقرب مستخدمٍ كتب حدثًا في الأوديت على نفس الموديل/المعرّف حول لحظة الحذف — أو null بصراحة */
    private function deletedByOf(string $type, Model $row): ?string
    {
        $deletedAt = $row->deleted_at;

        if ($deletedAt === null) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = self::RESOURCES[$type];

        $log = AuditLog::query()
            ->with('user')
            ->where('auditable_type', $model)
            ->where('auditable_id', $row->getKey())
            ->whereBetween('created_at', [
                $deletedAt->copy()->subMinutes(self::AUDIT_MATCH_MINUTES),
                $deletedAt->copy()->addMinutes(self::AUDIT_MATCH_MINUTES),
            ])
            ->latest('id')
            ->first();

        return $log?->user?->name;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(?User $actor, string $action, string $modelClass, int|string $id, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $modelClass,
            'auditable_id' => $id,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);
    }
}
