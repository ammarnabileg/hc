<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بنك أسئلة الحروب (12.10-ب · 24.2).
     *
     * لماذا جدول مستقلّ: القمع الموحّد (15.0) يسحب **70% من الساحة + 30% من
     * التدريبات**، والسحب لا يصحّ من JSON مزروع داخل الحرب — لأنّ الأسئلة
     * وقتها تكون معروفة ومكرّرة لكلّ اللاعبين وغير قابلة للإدارة.
     */
    public function up(): void
    {
        Schema::create('war_questions', function (Blueprint $table) {
            $table->id();
            $table->string('text', 500);
            // الإجابة تبقى في الخادم وحده ولا تُرسَل للمتصفح (15.2-3)
            $table->string('answer', 160)->nullable();
            $table->json('options')->nullable();
            // حرب التقدير تسحب الأسئلة الرقميّة وحدها (15.0 · 15.6)
            $table->boolean('is_numeric')->default(false)->index();
            $table->decimal('tolerance', 12, 4)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('difficulty', 16)->default('medium')->index();
            $table->string('source', 16)->default('arena')->index(); // arena · training
            $table->string('status', 16)->default('draft')->index();  // active · draft · archived
            $table->unsignedInteger('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'source', 'is_numeric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('war_questions');
    }
};
