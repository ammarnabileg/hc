<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('certificate_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique(); // course · path · event · volunteer_position · volunteer_experience …
            $table->string('name_ar');
            $table->string('name_en');
            $table->foreignId('accreditation_id')->nullable()->constrained('certificate_accreditations')->nullOnDelete();
            $table->boolean('auto_issue')->default(true);
            $table->string('numbering_prefix', 16)->nullable();
            $table->boolean('lang_ar_enabled')->default(true);
            $table->boolean('lang_en_enabled')->default(false);
            $table->boolean('signature_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
