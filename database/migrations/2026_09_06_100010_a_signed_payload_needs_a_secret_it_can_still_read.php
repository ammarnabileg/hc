<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🧩 المطوّرين — Webhooks (الدستور 12.15-ب · 12.15-ج · v5.5 قسمٌ جديد، سجلّ
 * القرارات 25). العميل السابق بنى تاب API (`api_keys` · `api_request_logs`)
 * وترك سقالة تاب Webhooks فارغة — هذه الهجرة تبني قاعدته.
 *
 * ⛔ **الفارق الجوهريّ عن `api_keys` — لا يُخلَط بينهما (12.15-ج):**
 * مفتاح الـAPI **يُقارَن** وقت الطلب (`Hash::check()`) فيكفيه Hash لا رجعة
 * فيه. أمّا سرّ الويب-هوك **يُشتقّ منه توقيعٌ HMAC** وقت كلّ إرسال، فلا بدّ
 * من فكّه — لذلك `secret_encrypted` بـ`Crypt::encryptString()` (تشفيرٌ
 * ذو مفتاحٍ عكسيّ، لا Hash أحاديّ الاتّجاه) لا نصًّا صريحًا أبدًا.
 *
 * `webhook_deliveries` سجلّ كلّ محاولة إرسال (12.15-ب): الحدث · الحمولة ·
 * الحالة (pending·success·failed·exhausted) · عدد المحاولات · موعد إعادة
 * المحاولة التالية — ويُقصّ `response_body` لطولٍ معقول فلا يتضخّم الجدول
 * بردودٍ ضخمة من وجهاتٍ لا تلتزم بعقد الويب-هوك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // اسم وصفيّ يختاره منشئ الويب-هوك (12.15-ب)
            $table->string('url', 2048);
            $table->json('events'); // مفاتيح من WebhookEventCatalog::EVENT_KEYS — كتالوجٌ مقفول
            $table->text('secret_encrypted'); // Crypt::encryptString() — يُفكّ وقت التوقيع، لا Hash
            $table->string('status', 16)->default('active'); // active · paused
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedSmallInteger('last_response_code')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->json('payload');
            $table->string('status', 16)->default('pending'); // pending · success · failed · exhausted
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable(); // مقصوص لطول معقول — راجع DeliverWebhookJob
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // سجلّ المحاولات لكلّ ويب-هوك (12.15-ب) وفرز «القادمة للمعالجة» بالحالة
            $table->index(['webhook_id', 'id']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
