<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تاب «ملاحظات إداريّة» (13.4-م-5): **للمخوَّل فقط · سرّيّة · بـAudit كامل**.
     *
     * لماذا جدول مستقلّ؟ لأنّ الملاحظة أداة قرار عند الترقية أو المشكلة، فلا تُخلَط
     * بملاحظات المهامّ ولا بسجلّ Rep، ولا تُعرَض لصاحب البروفايل أبدًا.
     */
    public function up(): void
    {
        Schema::create('volunteer_profile_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // صاحب البروفايل
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_profile_notes');
    }
};
