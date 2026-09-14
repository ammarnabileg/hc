<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ⭐⭐ **صفحة هبوط مستقلّة عن `bundles.edit`** (12.2.3 — مصفوفة `landing_pages`،
     * والدور «مسؤول التسويق والمتجر» في 12.2.3-ب-6 يحمل `landing_pages` **صلاحيّةً
     * منفصلة** عن `bundles.*`).
     *
     * ⚠️ **الفجوة التي تُسَدّ هنا:** كانت `landing_pages.*` صلاحيّاتٍ ميتة بالكامل —
     * موجودة في `permissions.json` ومسنَدة لـ`marketing_admin` (`RolePermissionSeeder`)
     * بلا مسارٍ أو متحكّمٍ أو جدولٍ يحرسه أيّ فعلٍ منها. والمُنجَز الوحيد فعليًّا
     * هو `BundleLanding` — **مبنيّ داخل شاشة تعديل البندل نفسها** ومحروسٌ بـ
     * `bundles.edit`/`bundles.view` (أعمدة على جدول `bundles`)، ولا صفحة هبوط
     * مستقلّة لمنتج المتجر (`store_products`) إطلاقًا.
     *
     * فهذا الجدول **كيانٌ قائمٌ بذاته** (`landingable` Polymorphic إلى `Bundle` أو
     * `Product`) بدورة حياته الستّاعيّة الست كما في المصفوفة حرفيًّا:
     * `view · create · edit · archive · restore · delete` — لا عمودًا إضافيًّا
     * على `bundles`/`products` يعيد نفس خطأ `BundleLanding` (اقترانٌ بالشاشة).
     *
     * والحقول **مقصودةٌ بسيطة لا Page-builder**: عنوانٌ ووعدٌ وصورة ونداءٌ للفعل
     * وقائمتا نتائج/أسئلة وجسمٌ حرّ — يكفي لصفحة تسويقيّة حقيقيّة دون تكرار
     * محرّك الوراثة/الحساب المعقّد في `BundleLanding` (ذاك خاصٌّ بتسعير البندل
     * ولا معنى له هنا).
     */
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();

            // الكيان المرتبط — بندل أو منتج، وواحدةٌ فقط لكلّ كيان (unique أدناه)
            $table->string('landingable_type');
            $table->unsignedBigInteger('landingable_id');

            $table->string('slug')->unique();
            // مسودّة ⟵ منشورة ⟵ مؤرشفة — والعرض العامّ لا يفتح إلّا «منشورة» (state:published)
            $table->string('status', 16)->default('draft')->index();

            // ------------------------------------------------------------ المحتوى
            $table->string('headline', 190)->nullable();
            $table->text('subheadline')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->json('outcomes')->nullable(); // «بعد كذا هتقدر…» — قائمة أسطر
            $table->json('faq')->nullable();      // [{q,a}, ...]
            $table->text('body')->nullable();     // فقرة حرّة إضافيّة قبل الإغلاق

            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // صفحة هبوط واحدة مستقلّة لكلّ كيان — لا تكرار صامت لنفس البندل/المنتج
            $table->unique(['landingable_type', 'landingable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};
