<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * مصفوفة الصلاحيّات الكاملة (الدستور 12.2.2) — ~1006 صلاحيّة على 8 مجموعات.
 * المصدر: database/data/permissions.json المستخرَج من المصفوفة المعتمَدة.
 */
class PermissionSeeder extends Seeder
{
    /** الأفعال القياسيّة الثلاثة عشر (12.2.1) */
    public const ACTIONS = [
        'view', 'list', 'create', 'edit', 'delete', 'approve', 'reject',
        'assign', 'archive', 'restore', 'export', 'import', 'manage',
    ];

    /** النطاقات الستّة — إلزاميّة ويُقيَّم داخل العضويّة النشطة */
    public const SCOPES = ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'];

    public function run(): void
    {
        $path = database_path('data/permissions.json');

        if (! File::exists($path)) {
            $this->command?->warn('ملفّ الصلاحيّات غير موجود: database/data/permissions.json');

            return;
        }

        $rows = json_decode(File::get($path), true) ?? [];

        foreach (array_chunk($rows, 200) as $chunk) {
            $payload = [];

            foreach ($chunk as $row) {
                $payload[] = [
                    'key' => $row['key'],
                    'resource' => $row['resource'],
                    'action' => $row['action'],
                    'group' => $row['group'],
                    'label_ar' => $row['label_ar'],
                    'description' => $row['description'] ?: null,
                    'allowed_scopes' => json_encode($row['allowed_scopes'], JSON_UNESCAPED_UNICODE),
                    'condition_key' => $row['conditions'][0] ?? null,
                    'is_sensitive' => (bool) $row['is_sensitive'],
                    // عزل الحسّاس: المجموعة المحميّة لمالك المنصّة وحده (12.2.1)
                    'is_owner_only' => (bool) $row['is_owner_only'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Permission::upsert(
                $payload,
                ['key'],
                ['resource', 'action', 'group', 'label_ar', 'description', 'allowed_scopes', 'condition_key', 'is_sensitive', 'is_owner_only', 'updated_at'],
            );
        }

        $this->command?->info('الصلاحيّات: '.Permission::count().' (منها '.Permission::where('is_sensitive', true)->count().' حسّاسة 🔒)');
    }
}
