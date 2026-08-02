<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('verification_type', 24)->default('numeric'); // numeric · boolean
            $table->decimal('target_from', 14, 2)->nullable();
            $table->decimal('target_to', 14, 2)->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('priority')->default(2);
            $table->decimal('progress_percent', 5, 2)->default(0); // الصعود الآليّ للنِّسَب
            $table->string('status', 24)->default('draft')->index(); // draft · sent_to_execution · active · completed · closed
            $table->timestamp('sent_to_execution_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
