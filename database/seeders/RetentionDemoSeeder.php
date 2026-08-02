<?php

namespace Database\Seeders;

use App\Models\RepRule;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * مجال الاحتفاظ (13.4-س · 13.4-ن-أ).
 *
 * يكمّل **جدول Rep الموحَّد** بالقيمتين الناقصتين المنصوصتين في الدستور،
 * ويضيف إعدادات سلّم الخمول ونافذة المكتسَب التراكميّ — كلّها قيمٌ من
 * الجدول والإعدادات لا أرقام محروقة (2.13).
 *
 * ولا يُسجَّل في `DatabaseSeeder` (BUILD.md §7).
 */
class RetentionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->repRules();
        $this->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');
    }

    /**
     * القيمتان الناقصتان من جدول Rep (13.4-ن-أ):
     *  · **قرار الحالة 8 اليدويّ** — مدًى من +0.25 إلى −0.5، وحدّاه إعدادان
     *    يقرأهما محرّك التصعيد؛ والقيمة هنا هي حدّه الأعلى.
     *  · **فرصة لجنة التحقيق** — +1 لمعدّل الالتزام مع إعادة التفعيل.
     */
    private function repRules(): void
    {
        $rows = [
            [
                'tasks', 'task.state8_manual', 'قرار الحالة 8 اليدويّ (الإرجاع المتكرّر)', 0.25,
                'مدًى يدويّ بمبرّر: من +0.25 إلى −0.5 — والحدّان في workflow.repeated_return.rep_max/rep_min.',
            ],
            [
                'tasks', 'task.committee_chance', 'فرصة لجنة التحقيق', 1.0,
                'قرار اللجنة بمنح فرصة: +1 لمعدّل الالتزام مع إعادة التفعيل.',
            ],
        ];

        foreach ($rows as [$group, $key, $label, $value, $note]) {
            RepRule::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'value' => $value,
                'note' => $note,
            ]);
        }
    }

    /** إعدادات المجال — دورة الخصم ونافذة المكتسَب التراكميّ */
    public function settings(): void
    {
        $rows = [
            ['rep.inactivity.deduction_every_days', 'rep', 'دورة خصم الخمول (أيّام)', 'number', '7'],
            ['volunteer.offboarding.cumulative_window_days', 'offboarding', 'نافذة المكتسَب التراكميّ (يوم)', 'number', '90'],
        ];

        foreach ($rows as [$key, $group, $label, $type, $default]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
            ]);
        }
    }
}
