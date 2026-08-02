<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صفحة المعلومات = **بيانات الشهادات والإفادات** (2.5-ج).
 *
 * لماذا أعمدة جديدة ولا يكفي `name` الواحد؟ لأنّ الشهادة تُطبَع باسم صاحبها
 * **بالعربيّ أو بالإنجليزيّ حسب لغة النسخة**، ويسبقه **لقبه**. فباسمٍ واحد
 * لا نعرف أيّهما يُطبَع، والنتيجة شهادةٌ باسمٍ ناقص أو بلغة غير لغتها — وهذا
 * عيبٌ لا يُصلَح بعد الإصدار لأنّ الشهادة تُجمَّد لحظة صدورها (12.5-ج).
 *
 * و«العنوان الفرعيّ» بندٌ منصوص في 2.5-ج بجانب النوع والدولة والمحافظة
 * (وهذه الثلاثة موجودة من قبل) — فيكتمل بها العنوان على الإفادة والسيرة.
 *
 * وأعمدة الرحلة (2.5-د) تُسجَّل على المستخدم نفسه لا في جدول جانبيّ، لأنّ كلًّا
 * منها **حدثٌ يقع مرّةً واحدةً في العمر** ويُقرَأ في كلّ طلب لتحديد الخطوة التالية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ---------------- بيانات الشهادات والإفادات (2.5-ج)
            $table->string('title', 64)->nullable()->after('code');
            $table->string('name_ar', 190)->nullable()->after('name');
            $table->string('name_en', 190)->nullable()->after('name_ar');
            $table->string('address_line', 255)->nullable()->after('governorate_id');

            // ---------------- رحلة ما بعد التسجيل (2.5-د)
            $table->timestamp('instructions_agreed_at')->nullable()->after('activated_by');
            $table->timestamp('placement_completed_at')->nullable()->after('instructions_agreed_at');
            $table->unsignedTinyInteger('placement_score')->nullable()->after('placement_completed_at');
            $table->timestamp('acceptance_seen_at')->nullable()->after('placement_score');
        });

        /*
         | ترحيل الحسابات القائمة — قاعدتان:
         |
         | 1. الاسم الواحد يُنسَب للغته حسب حروفه، فلا يبقى حسابٌ قديم بلا اسمٍ على
         |    شهادته. والفارغ يُملأ من صاحبه عند أوّل تعديل من الإعدادات.
         |
         | 2. ⭐ **الخطوات الجديدة لا تُطبَّق بأثرٍ رجعيّ**: مَن دخل المنصّة بالفعل
         |    مرّ من بابها القديم واعتمده الأدمن؛ فلو تركنا خانات الرحلة فارغةً
         |    لَوَجد كلّ مستخدمٍ قائم نفسه فجأةً أمام «التعليمات» و«الاختبار
         |    التمهيديّ» بعد ترقيةٍ لم يطلبها. فتُختَم رحلتُه بوقت تفعيله.
         */
        $now = now();

        DB::table('users')->orderBy('id')->chunkById(500, function ($rows) use ($now) {
            foreach ($rows as $row) {
                $update = [];
                $name = trim((string) $row->name);

                if ($name !== '') {
                    $update[preg_match('/\p{Arabic}/u', $name) ? 'name_ar' : 'name_en'] = $name;
                }

                if (($row->status ?? null) === 'active') {
                    $passed = $row->activated_at ?? $row->created_at ?? $now;
                    $update['instructions_agreed_at'] = $passed;
                    $update['placement_completed_at'] = $passed;
                    $update['acceptance_seen_at'] = $passed;
                }

                if ($update !== []) {
                    DB::table('users')->where('id', $row->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title', 'name_ar', 'name_en', 'address_line',
                'instructions_agreed_at', 'placement_completed_at', 'placement_score', 'acceptance_seen_at',
            ]);
        });
    }
};
