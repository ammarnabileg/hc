<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط جانب المواجهة بجدول المشاركات القائم — بلا تعديل أعمدة قديمة.
     * `reached_index` لحرب البقاء: **الرابح مَن نجا أبعد** (15.5).
     */
    public function up(): void
    {
        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->unsignedBigInteger('war_match_id')->nullable()->after('challenge_id')->index();
            $table->integer('reached_index')->default(0)->after('score');
            $table->boolean('withdrew')->default(false)->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->dropColumn(['war_match_id', 'reached_index', 'withdrew']);
        });
    }
};
