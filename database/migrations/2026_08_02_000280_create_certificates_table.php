<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('hash', 64)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_type_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject'); // Course · LearningPath · Event · Position
            $table->string('language', 5)->default('ar');
            $table->json('template_snapshot')->nullable(); // تجميد نسخة التصميم (12.5-ج)
            $table->json('data_snapshot')->nullable();
            $table->string('source', 24)->default('auto'); // auto · manual · import
            $table->timestamp('issued_at');
            // الحالات: valid · expired (منتهية — 13.4-ق) · revoked (ملغاة: تزوير فقط)
            $table->string('status', 24)->default('valid')->index();
            $table->timestamp('expired_at')->nullable();
            $table->string('expired_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'certificate_type_id']);
        });
    }
};
