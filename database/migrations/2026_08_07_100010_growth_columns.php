<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة حلقات النموّ والإعلان المدفوع (21.1 · 21.3).
 *
 * لماذا على `users` لا في الجلسة؟ لأنّ التسجيل ينتهي إلى «تحت المراجعة»، وبعد
 * الاعتماد الإداريّ تبدأ **جلسة جديدة** — فلو حفظنا وجهة الدعوة في الجلسة ضاعت.
 * فتُحفَظ على المستخدم نفسه وتُستهلك مرّةً واحدة عند أوّل دخول بعد الاعتماد (21.1-ج).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // بار «أكمل ملفك» ومكافأته 3 تذاكر — والمكافأة مرّة واحدة بحارس زمنيّ (21.1-ب)
            $table->unsignedTinyInteger('profile_completion_percent')->default(0);
            $table->timestamp('profile_completion_rewarded_at')->nullable();

            // وجهة رابط الدعوة العميق: «يُفتَح على نفس الصفحة بعد التسجيل» (21.1-ج)
            $table->string('invite_landing_url', 512)->nullable();
            $table->timestamp('invite_landing_seen_at')->nullable();

            // «تخصيص» في بانر الموافقة: أغراضٌ مسموحة بعينها لا قبولٌ أعمى (21.3-د)
            $table->json('tracking_scopes')->nullable();
        });

        Schema::table('tracking_events', function (Blueprint $table) {
            // الحدث نفسه يُرسَل من المتصفّح ومن الخادم بنفس Event ID — فنسجّل القناتين (21.3-أ)
            $table->boolean('sent_browser_side')->default(false);
            $table->string('platform', 16)->nullable();      // meta · google
            $table->string('consent', 16)->nullable();       // لقطة الموافقة لحظة الحدث
            $table->timestamp('dispatched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_completion_percent',
                'profile_completion_rewarded_at',
                'invite_landing_url',
                'invite_landing_seen_at',
                'tracking_scopes',
            ]);
        });

        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropColumn(['sent_browser_side', 'platform', 'consent', 'dispatched_at']);
        });
    }
};
