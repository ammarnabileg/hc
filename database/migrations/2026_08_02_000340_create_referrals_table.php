<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // الريفيرال 7% + مكافأة الطرفين: المدعوّ تذكرة ترحيب (21.1)
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 32)->index();
            $table->string('landing_type', 32)->nullable();  // deep link لكلّ محتوى (21.1)
            $table->unsignedBigInteger('landing_id')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(7);
            $table->decimal('commission_earned', 12, 2)->default(0);
            $table->boolean('welcome_ticket_granted')->default(false);
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();
        });
    }
};
