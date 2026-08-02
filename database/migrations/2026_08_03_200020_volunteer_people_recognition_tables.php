<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حائط الشكر (13.4-ي): تاب نقاش بـVote — والتصويت يستعمل `post_votes` المورفيّ القائم
 * فلا جدول تصويت ثانٍ في المنصّة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thanks_wall_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('thanks_wall_posts')->nullOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->integer('votes')->default(0);
            // السباق شهريّ متجدّد مع التصفير الشهريّ (13.4-ن)
            $table->string('month_key', 7)->index(); // YYYY-MM
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thanks_wall_posts');
    }
};
