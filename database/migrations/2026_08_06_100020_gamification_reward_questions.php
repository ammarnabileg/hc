<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بنك أسئلة المكافأة (12.10-أ).
 *
 * بنك **منفصل تمامًا** عن أسئلة الدرس وأسئلة الحروب — الغرض منه أن يكسب
 * المتدرّب مكافأةً حين يجاوب داخل نافذة زمنيّة، فلكلّ سؤال **رابط مؤقّت**
 * ينشره الأدمن (واتساب/QR)، وفوق السؤال **تايمر نازل**، وبعد انتهاء الوقت
 * يقفل الرابط ويظهر «انتهى وقت الإجابة».
 *
 * لماذا `token` عمود مستقلّ لا المعرّف؟ لأنّ الرابط يُنشَر في جروبات عامّة،
 * والمعرّف المتسلسل يكشف بقيّة الأسئلة بالتخمين.
 *
 * ولماذا قيد فريد على (سؤال، مستخدم)؟ لأنّ «حدّ إجابة واحدة لكلّ مستخدم»
 * ومنع تكرار الصرف قاعدتان لا تُؤمَّنان بشرطٍ في الكود وحده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_questions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();          // مفتاح الرابط العامّ
            $table->text('prompt');
            $table->string('type', 16)->default('choice');  // choice · text · number
            $table->json('options')->nullable();
            $table->text('correct_answer');                 // لا تُرسَل للمتصفّح أبدًا
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_tickets')->default(0);
            $table->unsignedInteger('active_minutes')->default(60); // مدّة التفعيل
            $table->timestamp('opens_at')->nullable();      // جدولة الفتح التلقائيّ
            $table->timestamp('closes_at')->nullable();     // تُحسَب من المدّة أو تُغلَق يدويًّا
            $table->string('status', 16)->default('draft')->index(); // draft · published · archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reward_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('tickets_awarded')->default(0);
            $table->timestamp('answered_at');
            $table->timestamps();

            // إجابة واحدة لكلّ مستخدم لكلّ سؤال — ومنع تكرار الصرف (12.10-أ)
            $table->unique(['reward_question_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_question_answers');
        Schema::dropIfExists('reward_questions');
    }
};
