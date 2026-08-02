<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // التصدير حسّاس — والبيانات تُشفَّر SHA-256 ولا تغادر خامًا (21.3-ج/د)
        Schema::create('ad_audience_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exported_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('rows')->default(0);
            $table->string('file_path')->nullable();
            $table->string('hash_algo', 16)->default('sha256');
            $table->timestamps();
        });
    }
};
