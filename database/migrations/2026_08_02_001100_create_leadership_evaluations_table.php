<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // تقييم أسبوعيّ من الداونلاين للأبلاين — مجهول، وعتبة 3 مقيّمين (23)
        Schema::create('leadership_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluatee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_start');
            $table->json('criteria_scores'); // {criterion_key: 0..10}
            $table->decimal('average', 4, 2);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['evaluator_id', 'evaluatee_id', 'week_start']);
        });
    }
};
