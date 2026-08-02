<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ⭐ «أقصى XP للدرس» — مصدرٌ واحد: `courses.xp_max` (7 · 2.13).
 *
 * كان للقيمة الواحدة **ثلاثة أعمدة**: الفورم يكتب في `max_lesson_xp`، والحاسبة
 * تقرأ `xp_max` ثمّ ترتدّ إلى `xp_before_half`. وبما أنّ `xp_max` كان صفرًا في
 * كلّ البيانات، فالنظام كان يعمل فعليًّا على `xp_before_half` — وهو المفهوم
 * الذي **أُلغي دستوريًّا** (القرار «ب» في القسم 7: تناقص خطّيّ حتى الصفر، ولا
 * «نصف مهلة» للـXP). والنتيجة: الأدمن يعدّل رقمًا بلا أثر.
 *
 * هذه المايجريشن ترحّل القيمة إلى العمود المعتمَد بالأولويّة الصحيحة، فلا
 * يفقد تدريبٌ قائم إعداده. ولا تُحذَف الأعمدة القديمة (حذف الأعمدة خارج ملكيّة
 * المجال — دليل البناء §1)، لكن **لم يبقَ لها قارئٌ ولا كاتب في الكود**.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'xp_max')) {
            return;
        }

        $legacy = collect(['max_lesson_xp', 'xp_before_half', 'xp_after_half'])
            ->filter(fn (string $column) => Schema::hasColumn('courses', $column));

        if ($legacy->isEmpty()) {
            return;
        }

        // الأولويّة: ما كتبه الفورم أوّلًا، ثمّ قيمتا «نصف المهلة» الملغاتان
        $expression = $legacy
            ->map(fn (string $column) => 'NULLIF('.$column.', 0)')
            ->implode(', ');

        DB::table('courses')
            ->where(fn ($q) => $q->whereNull('xp_max')->orWhere('xp_max', 0))
            ->update(['xp_max' => DB::raw('COALESCE('.$expression.', 0)')]);
    }

    public function down(): void
    {
        // ترحيلُ قيمةٍ لا يُعكَس: العمود المعتمَد يبقى حاملًا للحقيقة.
    }
};
