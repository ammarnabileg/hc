<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // الرقم الظاهر مسقوف −10…+10 ويتصفّر يوم 1 الساعة 5:00ص القاهرة (13.4-ن)
        Schema::create('rep_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->decimal('daily_loss_today', 5, 2)->default(0); // حدّ الخسارة اليوميّ −2
            $table->date('daily_loss_date')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }
};
