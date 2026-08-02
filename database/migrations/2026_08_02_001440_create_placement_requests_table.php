<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // قفل ذرّيّ + طلب معلَّق واحد + مهلة ردّ المرشّح 48 ساعة (13.4-هـ)
        Schema::create('placement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('sent')->index(); // sent · accepted · rejected · withdrawn · expired
            $table->timestamp('respond_due_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }
};
