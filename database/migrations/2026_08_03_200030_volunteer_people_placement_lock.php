<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الأقفال الثلاثة للتسكين (13.4-هـ).
 *
 * لماذا عمود على المرشّح لا مجرّد استعلام على الطلبات؟
 * لأنّ «طلبًا معلَّقًا واحدًا» قيدٌ على **المرشّح** لا على الطلب — فحمله في صفّ المرشّح
 * يجعل القفل الذرّيّ ممكنًا بتحديثٍ شرطيّ (Compare-and-Swap) يعمل على أيّ قاعدة بيانات:
 * أوّل مشرف يكسب الصفّ، والثاني يرجع بصفر صفوف متأثّرة فيُرفَض بلا سباق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->unsignedBigInteger('pending_placement_request_id')->nullable()->index();
            // المُسكَّن لا يختفي بل يصير غير مفعَّل ويظلّ ظاهرًا للمخوَّلين (13.4-هـ)
            $table->boolean('is_active_in_list')->default(true)->index();
        });

        Schema::table('placement_requests', function (Blueprint $table) {
            $table->string('note')->nullable();          // سبب إعادة الإرسال (اختياريّ)
            $table->string('respond_note')->nullable();   // ردّ المرشّح
        });
    }

    public function down(): void
    {
        Schema::table('placement_requests', function (Blueprint $table) {
            $table->dropColumn(['note', 'respond_note']);
        });

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn(['pending_placement_request_id', 'is_active_in_list']);
        });
    }
};
