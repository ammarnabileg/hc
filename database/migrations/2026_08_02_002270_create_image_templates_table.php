<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('image_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('purpose', 48)->default('marketing')->index(); // marketing · leaderboard · achievement · volunteer_card
            $table->unsignedInteger('width_px');
            $table->unsignedInteger('height_px');
            $table->string('preset', 32)->nullable(); // square · story · cover · whatsapp · custom
            $table->string('frame_path')->nullable();
            $table->json('layers')->nullable(); // نصوص وصورة المستخدم بترتيبها ورفعها/إنزالها
            $table->string('audience', 32)->default('admin'); // admin · volunteers · everyone
            $table->string('language', 5)->default('ar');
            $table->json('folders')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false); // المستخدَم في نشرٍ قائم يُؤرشَف لا يُحذَف
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
