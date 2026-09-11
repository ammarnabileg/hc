<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة 23-0.3: «~~اجتماع (Meeting)~~ ممنوع كنوع مهمّة — والبديل
 * الفعاليّات بكود الحضور، **وتقدر تولّد مهمّة «تنفيذ» لبنود المحضر**».
 *
 * الشقّ الأوّل كان قائمًا (الاجتماع فعاليّة لا نوع مهمّة)، أمّا **التوليد**
 * فكان غائبًا تمامًا: `AttendanceService::end()` يحفظ `minutes` نصًّا حرًّا
 * وينتهي الأمر — فبند المحضر يبقى كلامًا غير متتبَّع خارج شجرة الأهداف.
 *
 * ولماذا عمود صريح لا حقل عامّ (Polymorphic)؟ لأنّ `tasks` ليس فيها أصلًا أيّ
 * أصل عامّ: عمود `source` سلسلةٌ تصف **كيف** جاءت المهمّة (`assigned` ·
 * `public_board` · `recurring`) ولا تحمل **من أين**. والاصطلاح القائم في هذه
 * القاعدة لأصلٍ من جدولٍ بعينه هو مفتاحٌ أجنبيّ قابل للإفراغ — كما في
 * `investigation_cases.meeting_id` — فاتّبعناه بلا اختراع طبقةٍ جديدة.
 *
 * والعمود **قابل للإفراغ**: كلّ مهمّة قائمة تبقى كما هي بلا أصلٍ اجتماعيّ،
 * وحذف الاجتماع يُفرِغ الأصل ولا يمسّ المهمّة — فهي بعد توليدها مهمّةٌ عاديّة
 * تمامًا: تدخل سقف الانشغال والـRoll-up والمراجعة كإخوتها بلا استثناء.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('source_meeting_id')->nullable()->after('blocked_by_task_id')
                ->constrained('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_meeting_id');
        });
    }
};
