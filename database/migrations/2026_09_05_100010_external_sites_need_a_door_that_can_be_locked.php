<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🧩 المطوّرين — API (الدستور 12.15-أ · v5.5 قسمٌ جديد، سجلّ القرارات 25).
 *
 * أمر المالك المباشر: «جهّز تاب في الأدمن بانل دروب-داون اسمها المطوّرين،
 * وضيف تاب اسمها API وواحدة تانية اسمها Webhooks، عشان لو هربط أيّ مواقع مع
 * المنصّة». الصلاحيّات `integrations.*` و`webhooks.*` و`rate_limits.*` كانت
 * **مزروعةً في مصفوفة 12.2.2 بلا شاشةٍ تستخدمها** — فهذه الهجرة تبني القاعدة
 * التي تسدّ الفجوة، لا صلاحيّاتٍ جديدة.
 *
 * ⛔ **قيدان أمنيّان لا يُمَسّان (12.15-ج):**
 * 1) المفتاح الكامل **لا يُخزَّن نصًّا صريحًا أبدًا** — `key_hash` وحده
 *    (`Hash::make()`، نفس أسلوب توكن استرجاع كلمة المرور)، و`key_prefix`
 *    عرضٌ بصريّ فقط (أوّل خانات المفتاح) لا يكفي للمصادقة.
 * 2) كلّ طلب API **مُسجَّل** في `api_request_logs` — لا استثناء صامت
 *    (12.15-ج). والحدّ «آخر 100 لكلّ مفتاح» (12.15-أ) يُطبَّق بأمرٍ مجدولٍ
 *    منفصل لا بقيدٍ في هذه الهجرة — فالتنظيف عمليّة دوريّة لا قيد بنيويّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // اسم وصفيّ يختاره منشئ المفتاح (12.15-أ)
            $table->string('key_prefix', 12)->unique(); // أوّل خانات ظاهرة — تمييزٌ بصريّ فقط
            $table->string('key_hash'); // Hash::make() للمفتاح الكامل — لا نصّ صريح أبدًا
            $table->json('scopes'); // مصفوفة من القائمة المقفولة (ApiKeyService::SCOPES)
            $table->unsignedInteger('rate_limit_per_minute')->nullable(); // فارغ = يرث الحدّ العامّ
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->string('status', 16)->default('active'); // active · revoked
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code');
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            // آخر 100 سجلّ لكلّ مفتاح (12.15-أ) — الفهرس يخدم التنظيف الدوريّ والعرض معًا
            $table->index(['api_key_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_keys');
    }
};
