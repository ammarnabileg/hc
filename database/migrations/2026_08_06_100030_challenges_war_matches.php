<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * المواجهة بين محاربَين (15.1 · 15.5 · 15.6).
     *
     * `questions` لقطة الأسئلة **بإجاباتها** — تبقى في الخادم ولا تُرسَل
     * للمتصفح (15.2-3)، وهي **نفسها للطرفين** (15.2-8).
     */
    public function up(): void
    {
        Schema::create('war_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('war_type', 24)->index();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->json('questions');
            $table->string('status', 16)->default('running')->index(); // running · finished
            $table->timestamp('started_at');
            // أوّل مَن يخلّص يبدأ **عدّاد الحسم** الظاهر للطرفين (15.1)
            $table->timestamp('first_finished_at')->nullable();
            $table->timestamp('decision_deadline_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 16)->nullable(); // win · draw · withdraw
            $table->json('settlement')->nullable();    // كشف التسوية الصفريّة (15.2-6)
            $table->timestamps();

            $table->index(['challenger_id', 'status']);
            $table->index(['opponent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('war_matches');
    }
};
