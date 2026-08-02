<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('candidate_entity_fits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['recruitment_candidate_id', 'entity_id'], 'candidate_entity_unique');
        });
    }
};
