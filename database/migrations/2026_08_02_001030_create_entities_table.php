<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // الكيان = قسم رئيسيّ/فرعيّ أو محافظة أو ملفّ — شجرة واحدة
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 16)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('member_cap')->nullable(); // يُحسَب تلقائيًّا — مؤشّر لا مانع
            $table->string('status', 24)->default('active')->index(); // active · archived (الملفّ يُؤرشَف)
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
