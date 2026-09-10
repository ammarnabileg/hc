<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * أيتَمٌ حقيقيٌّ من `settings:coverage --dead` (2.13) — لا بلاغٌ كاذب.
 *
 * `events.certificate.code_prefix` (`EventDemoSeeder`) مزروعٌ ولا يقرؤه أحد:
 * جسر شهادة الفعاليّة الفعليّ (`CertificateBridge` ⟵ `CertificateIssuer::issue()`
 * ⟵ `CertificateNumber::next()`) يقرأ `$type->numbering_prefix` — عمودٌ على
 * `CertificateType` نفسه، مضبوطٌ لكلّ نوع شهادةٍ على حدة من شاشة إدارة
 * الشهادات (`admin/certificates/partials/types.blade.php`)، لا من إعدادٍ
 * عامٍّ واحد. هذا هو نفس الفئة (3) من مايجريشن
 * `2026_09_04_100010_dead_flags_from_an_aborted_first_pass_get_swept`:
 * **مفتاحٌ استُبدِل بآلية أخرى تُقرَأ فعلًا** — وهنا أدقّ من الاستبدال المعتاد
 * لأنّ `numbering_prefix` يحقّق 12.5-ب («نظام ترقيمٍ مخصّصٌ لكلّ نوع») بدقّةٍ
 * لا يقدر عليها مفتاحٌ عامٌّ واحد أصلًا (ثماني أنواع شهاداتٍ، بادئةٌ مختلفة
 * لكلٍّ منها — `CoreSeeder::certificateTypes()`).
 *
 * ولا وصلَ ممكنًا هنا حتى لو أُريد: `CoreSeeder::certificateTypes()` يزرع
 * `numbering_prefix` الابتدائيّ ضمن `DatabaseSeeder` **قبل**
 * `SettingDefinitionsSeeder` (الذي يستدعي `EventDemoSeeder::settings()` ويزرع
 * هذا المفتاح)، فأيّ قراءةٍ للمفتاح من هناك كانت سترتدّ لقيمته الافتراضيّة هي
 * نفسها بلا أثر.
 *
 * حُذف موضعُه من `EventDemoSeeder` في نفس الدفعة — وإلّا عاد في أوّل
 * `migrate:fresh --seed`.
 */
return new class extends Migration
{
    private const DEAD_KEYS = [
        'events.certificate.code_prefix',
    ];

    public function up(): void
    {
        $ids = DB::table('settings')->whereIn('key', self::DEAD_KEYS)->pluck('id');

        DB::table('setting_overrides')->whereIn('setting_id', $ids)->delete();
        DB::table('settings')->whereIn('key', self::DEAD_KEYS)->delete();
    }

    /**
     * لا عكس: يتيمٌ حقيقيٌّ بلا قارئ — إعادته إعادةُ وعدٍ كاذب لا تراجعٌ عن
     * خطأ. الاسترجاع الصحيح من نسخة احتياطيّة إن لزم.
     */
    public function down(): void
    {
        // بلا عكس — انظر التعليق أعلاه.
    }
};
