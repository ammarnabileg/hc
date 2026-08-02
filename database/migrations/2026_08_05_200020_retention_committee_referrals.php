<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إحالات لجنة التحقيق (13.4-س-ج).
 *
 * لماذا؟ لأنّ مسار اللجنة يُفتَح من **بابين لا باب واحد**: بلوغ عتبة التعليق
 * على الرقم الظاهر (−10)، و**مجموع Rep المكتسَب خلال 90 يومًا ≤ −15** — وهذا
 * الثاني هو ما يسدّ ثغرة التصفير الشهريّ. فبلا صفٍّ يوثّق الإحالة لا نعرف
 * أفُتِح المسار أصلًا، ويتكرّر فتحه كلّ يوم على نفس الشخص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_committee_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // سبب الإحالة: displayed_threshold (−10) · cumulative_90d (−15/90 يومًا)
            $table->string('trigger', 32)->index();
            $table->decimal('threshold', 8, 2);
            $table->decimal('value', 8, 2);
            $table->unsignedSmallInteger('window_days')->nullable();
            $table->string('status', 24)->default('open')->index(); // open · closed
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_committee_referrals');
    }
};
