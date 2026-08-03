<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * القنوات الموحّدة من مكان واحد (12.6-أ): **تاب · Toast/إشعار · بريد** —
 * كلّ قناة مستقلّة يختارها الأدمن في المحرّر نفسه.
 *
 * ولماذا جدول تسليم مستقلّ؟ لأنّ البريد **لا يُسترجَع بعد إرساله**: فبلا صفٍّ
 * يُثبت أنّ هذا المنشور وصل هذا المستخدم، كلّ إعادة تشغيلٍ للجدولة تعني رسالةً
 * مكرّرة في بريد الناس. الصفّ + فهرس فريد = الإرسال لا يُعيد نفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // قناة التاب: المنشور يظهر في فيد التعليمات — مفعّلة افتراضيًّا
            $table->boolean('show_in_feed')->default(true)->after('push_to_notifications');
            // قناة البريد: مطفأة افتراضيًّا — البريد لا يُفتَح على الناس بالغلط
            $table->boolean('email_enabled')->default(false)->after('show_in_feed');
        });

        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16)->default('email');
            // sent · deferred (حدّ الهدوء) · failed · skipped (تفضيل المستخدم أو حالته)
            $table->string('status', 16)->default('sent');
            $table->string('reason', 190)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('deferred_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // ⭐ حارس التكرار: صفٌّ واحد لكلّ (منشور × مستخدم × قناة) مهما تكرّر التشغيل
            $table->unique(['announcement_id', 'user_id', 'channel'], 'announcement_deliveries_unique');
            $table->index(['channel', 'status', 'deferred_until']);
            $table->index(['user_id', 'channel', 'sent_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // تفضيل المستخدم لقناة البريد — والفحص على الخادم لا في الواجهة (12.6-أ)
            $table->timestamp('email_optout_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['show_in_feed', 'email_enabled']);
        });

        Schema::dropIfExists('announcement_deliveries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_optout_at');
        });
    }
};
