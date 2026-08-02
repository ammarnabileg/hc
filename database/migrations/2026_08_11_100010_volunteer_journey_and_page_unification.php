<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * رحلة المتطوّع (13.4-أ · ب · ج · هـ · ل) — توحيد المفاتيح وأعمدة الرحلة.
 *
 * **العطل الذي تُصلحه:** كانت صفحة «تطوّع معنا» تقرأ `volunteering.landing.*`
 * والأدمن يكتب في `volunteer_page.*` — مفتاحان لمعنًى واحد، فيكتب المالك
 * محتوًى حقيقيًّا والصفحة تعرض «محتوى الصفحة بيتجهّز». والقاعدة (2.13):
 * **مفتاح واحد لكلّ معنًى** — فنُرحّل القيم القديمة إلى مفاتيح الأدمن ثمّ
 * نحذف اليتيم، فلا يبقى مفتاحٌ بلا قارئ ولا قارئٌ بلا مفتاح.
 *
 * ومعها ثلاثة أعمدة/جداول تحتاجها الرحلة:
 *  - **ميثاق المتطوّع** يُوافَق عليه **قبل بدء التأهيليّ** (13.4-أ).
 *  - **«جدّد استعدادك»** بمهلة تبريد تمنع التكرار المتلاحق (13.4-هـ).
 *  - **حارس «مرّة واحدة»** لمكافآت المسارات (1000 XP التأهيليّ · Rep الأكاديميّة)
 *    — والقيد الفريد في قاعدة البيانات هو الحارس الأخير لا شرط `if` في الكود.
 */
return new class extends Migration
{
    /** المفاتيح اليتيمة ⟵ مفاتيح الأدمن المعتمَدة */
    private const KEY_MAP = [
        'volunteering.landing.title' => 'volunteer_page.hero_title',
        'volunteering.landing.body' => 'volunteer_page.hero_subtitle',
        'volunteering.landing.cta' => 'volunteer_page.cta_label',
        'volunteering.landing.sections' => 'volunteer_page.blocks',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ميثاق المتطوّع: تعهّد يُوافَق عليه مرّة قبل بدء التأهيليّ (13.4-أ)
            $table->timestamp('volunteer_charter_accepted_at')->nullable();
        });

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            // تبريد «جدّد استعدادك» — يُقاس من آخر تجديد لا من تاريخ التقديم (13.4-هـ)
            $table->timestamp('readiness_renewed_at')->nullable();
        });

        Schema::create('volunteer_path_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            // qualifying_xp (13.4-ب) · academy_rep (13.4-ل)
            $table->string('kind', 32);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'learning_path_id', 'kind'], 'volunteer_path_award_unique');
        });

        $this->unifyPageKeys();
        $this->qualifyingCourseToPath();
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_path_awards');

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn('readiness_renewed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('volunteer_charter_accepted_at');
        });
    }

    /**
     * ترحيل بلا يتامى: قيمة المفتاح القديم تنتقل إلى الجديد **إن كان الجديد
     * ما زال على افتراضيّه أو فارغًا** — فلا يدهس القديمُ محتوًى كتبه المالك.
     */
    private function unifyPageKeys(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::KEY_MAP as $old => $new) {
            $legacy = DB::table('settings')->where('key', $old)->first();

            if (! $legacy) {
                continue;
            }

            $target = DB::table('settings')->where('key', $new)->first();
            $legacyValue = $legacy->value;
            $isEmpty = $legacyValue === null || $legacyValue === '' || $legacyValue === '[]';

            if ($target && ! $isEmpty) {
                $untouched = $target->value === null
                    || $target->value === ''
                    || $target->value === '[]'
                    || (string) $target->value === (string) $target->default_value;

                if ($untouched) {
                    DB::table('settings')->where('key', $new)->update(['value' => $legacyValue, 'updated_at' => now()]);
                }
            }

            DB::table('settings')->where('key', $old)->delete();
        }
    }

    /**
     * 13.4-ب هو الحاكم: التأهيليّ **مسارٌ** شامل كورسات لا كورسًا واحدًا
     * (و13.4-ق يصف الشهادة نفسها لا آليّة ربطها). فنُرحّل القيمة القائمة:
     * كورسُ التأهيليّ ⟵ **المسار الذي يضمّه**، ولا نترك المفتاح القديم يتيمًا.
     */
    private function qualifyingCourseToPath(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $legacy = DB::table('settings')->where('key', 'volunteer.qualifying.course_id')->first();

        if (! $legacy) {
            return;
        }

        $pathId = 0;
        $courseId = (int) $legacy->value;

        if ($courseId > 0 && Schema::hasTable('course_learning_path')) {
            $pathId = (int) DB::table('course_learning_path')
                ->where('course_id', $courseId)
                ->orderBy('learning_path_id')
                ->value('learning_path_id');
        }

        $exists = DB::table('settings')->where('key', 'volunteer.qualifying.path_id')->exists();

        if ($exists) {
            if ($pathId > 0) {
                DB::table('settings')
                    ->where('key', 'volunteer.qualifying.path_id')
                    ->where(fn ($q) => $q->whereNull('value')->orWhere('value', '')->orWhere('value', '0'))
                    ->update(['value' => (string) $pathId, 'updated_at' => now()]);
            }
        } else {
            DB::table('settings')->insert([
                'key' => 'volunteer.qualifying.path_id',
                'group' => 'recruitment',
                'label_ar' => 'المسار التأهيليّ (13.4-ب)',
                'type' => 'number',
                'value' => (string) $pathId,
                'default_value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('settings')->where('key', 'volunteer.qualifying.course_id')->delete();
    }
};
