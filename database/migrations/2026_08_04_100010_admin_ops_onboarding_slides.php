<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // شرائح الترحيب المتدرّجة و«شاشة أوّل مرّة» (12.7-أ · 2.15-د).
        // لماذا جدول واحد للاثنين؟ لأنّ المرحلة واحدة في المعنى: عنوان ونصّ وصورة
        // وزرّ إجراء وترتيب — والفرق فقط في الشاشة التي تظهر فوقها.
        Schema::create('onboarding_slides', function (Blueprint $table) {
            $table->id();
            // مفتاح الشاشة: `welcome` لسلسلة الترحيب الأولى، أو اسم مسار لـ«شاشة أوّل مرّة»
            $table->string('screen', 64)->default('welcome')->index();
            $table->string('title_ar');
            $table->text('body_ar')->nullable();
            $table->string('image_path')->nullable();
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // مِن قالب جاهز؟ يُعرَض للأدمن ليعرف ما عدّله بيده (2.15-د)
            $table->boolean('from_template')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['screen', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_slides');
    }
};
