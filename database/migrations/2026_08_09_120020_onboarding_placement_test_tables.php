<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الاختبار التمهيديّ (2.5-د-2) — الخطوة الثانية بعد «التعليمات» وقبل «تحت المراجعة».
 *
 * ⚠️ لا علاقة له بـ`placement_requests` (تسكين المتطوّعين في الهيكل — 13.4):
 * هذا **اختبارٌ يدخله كلّ مُسجَّل جديد** قبل أن يراه الأدمن، وغرضه التصفية
 * **بلا مال** كما نصّ 2.5-د. ولذلك جدولٌ مستقلّ باسمٍ مستقلّ لا التباس فيه.
 *
 * ولماذا ليس `exam_questions`؟ لأنّ أسئلة الامتحانات مرتبطة بامتحانٍ مرتبطٍ
 * بتدريب، والمُسجَّل الجديد لا تدريب له بعد. وأسئلة هذا الاختبار تحمل ما لا
 * تحمله تلك: **وسيط لكلّ سؤال** (فيديو/صورة/HTML مضمَّن) و**مكافأة لكلّ سؤال**
 * (XP فقط أو تذاكر فقط أو الاثنين) كما نصّ البند حرفيًّا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_test_questions', function (Blueprint $table) {
            $table->id();
            $table->text('prompt');

            // الوسيط: none · video · image · embed — والـHTML المضمَّن «من أيّ مكان» (2.5-د-2)
            $table->string('media_kind', 16)->default('none');
            $table->string('media_url', 1024)->nullable();
            $table->text('embed_html')->nullable();

            // الإجابات «مثل إجابات الاختبارات العادية» (2.5-د-2)
            $table->string('type', 16)->default('choice');   // choice · text
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();      // لا تُرسَل للمتصفّح أبدًا

            // مكافأة لكلّ سؤال: XP فقط أو تذاكر فقط أو الاثنين
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_tickets')->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('placement_test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_test_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('tickets_awarded')->default(0);
            $table->timestamp('answered_at');
            $table->timestamps();

            // إجابة واحدة لكلّ مستخدم لكلّ سؤال — القيد يمنع تكرار الصرف لا شرطٌ في الكود
            $table->unique(['placement_test_question_id', 'user_id'], 'placement_answer_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_test_answers');
        Schema::dropIfExists('placement_test_questions');
    }
};
