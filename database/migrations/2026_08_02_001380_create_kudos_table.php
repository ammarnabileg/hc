<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // حدود: 2/يوم · 7 أفراد/أسبوع · وسبب مكتوب إلزاميّ (13.4-ي)
        Schema::create('kudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->decimal('vxp_awarded', 12, 2)->default(20);
            $table->timestamps();
            $table->index(['sender_id', 'created_at']);
        });
    }
};
