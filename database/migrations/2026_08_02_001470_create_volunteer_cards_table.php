<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // بطاقة المتطوّع الرقميّة (13.4-ر): صفحة عامّة + صورة، وتصير «منتهية» بانتهاء العضويّة
        Schema::create('volunteer_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('hash', 64)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language', 5)->default('ar');
            $table->foreignId('image_template_id')->nullable();
            $table->json('data_snapshot')->nullable();
            $table->boolean('show_rep')->default(false); // افتراضيّ مخفيّ
            $table->string('status', 24)->default('valid')->index(); // valid · expired
            $table->timestamp('issued_at');
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }
};
