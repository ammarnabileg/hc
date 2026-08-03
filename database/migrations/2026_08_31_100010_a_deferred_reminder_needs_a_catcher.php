<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تذكيرات الفعاليّات (13.3 · 12.11): «**تذكيرات مجدولة** (قبل يوم/ساعة) عبر
 * الإشعارات/Toast/بريد».
 *
 * كانت الشاشة والفورم يَعِدان بالتذكير ولا أمرَ يلتقطه ولا جدولةَ تشغّله ولا
 * جدولَ يحفظ ما أُرسِل — و**التأجيل بلا مُلتقِط إسقاطٌ صامت**: وعدٌ في الواجهة
 * بلا سطرٍ واحد في القاعدة. وهذا الجدول هو **حارس عدم التكرار**: الفهرس الفريد
 * (فعاليّة · مستخدم · الموعد · القناة) يمنع وصول التذكير نفسه مرّتين مهما أُعيد
 * تشغيل الأمر أو تداخلت مسحتان.
 *
 * ولماذا صفٌّ لكلّ قناة لا صفٌّ واحد؟ لأنّ البريد قد يتعثّر والجرس ينجح؛ فلو
 * جمعناهما في صفٍّ واحد لَمنَع نجاحُ أحدهما إعادةَ محاولة الآخر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // الموعد بالدقائق قبل بداية الفعاليّة — قائمةٌ يحرّرها الأدمن (2.13)
            $table->unsignedInteger('offset_minutes');
            $table->string('channel', 16);
            $table->string('status', 16)->default('sent');
            $table->string('reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'offset_minutes', 'channel'], 'event_reminders_once');
            $table->index(['event_id', 'offset_minutes']);
        });

        /*
         | «إشعار المسجّلين» في شاشة المسجّلين والحضور (24.3): «نصّ + قناة +
         | **إرسال الآن/مجدول**». والمجدول بلا مستقرٍّ ولا مُلتقِطٍ **إسقاطٌ
         | صامت** — فالصفّ هنا هو المستقرّ، و`events:remind` هو المُلتقِط،
         | و`status` يُطالَب بتحوّلٍ ذرّيّ فلا يُرسَل الإشعار مرّتين.
         */
        Schema::create('event_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('channel', 16)->default('bell');
            $table->timestamp('send_at');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->string('status', 16)->default('pending')->index();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            // «تذكيرات مجدولة» خيارٌ في فورم الفعاليّة (12.11) — والافتراضيّ مفعَّل
            $table->boolean('reminders_enabled')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notices');
        Schema::dropIfExists('event_reminders');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });
    }
};
