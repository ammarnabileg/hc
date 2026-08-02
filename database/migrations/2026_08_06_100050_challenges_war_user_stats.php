<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجلّ المحارب: الفوز/الخسارة على كارت «المحاربون الجاهزون» (15.1)،
     * و`loss_streak` تنفيذًا لقاعدة **3 خسارات متتالية** (حماية من الانقطاع).
     * `focus_minutes` مكافأة حرب التركيز **غير الاقتصاديّة** (15.3).
     */
    public function up(): void
    {
        Schema::create('war_user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('withdrawals')->default(0);
            $table->unsignedInteger('loss_streak')->default(0)->index();
            $table->unsignedInteger('focus_minutes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('war_user_stats');
    }
};
