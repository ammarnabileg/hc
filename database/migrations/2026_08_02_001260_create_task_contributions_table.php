<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // المساهمون: بند بديدلاين داخليّ ≤ ديدلاين المهمّة −24 ساعة (23-4)
        Schema::create('task_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contributor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('item_title');
            $table->text('instructions')->nullable();
            $table->text('deliverable_spec')->nullable();
            $table->timestamp('internal_deadline_at');
            $table->decimal('vxp_value', 12, 2)->default(0);
            $table->string('vxp_source', 24)->default('task_pool'); // task_pool · owner_balance
            $table->decimal('held_amount', 12, 2)->default(0);      // الرصيد المعلَّق
            // invited · accepted · rejected · delivered · approved · returned · withdrawn · expired
            $table->string('status', 24)->default('invited')->index();
            $table->timestamp('invited_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('owner_review_due_at')->nullable(); // 24 ساعة ثمّ اعتماد تلقائيّ
            $table->timestamp('approved_at')->nullable();
            $table->boolean('auto_approved')->default(false);
            $table->timestamps();
        });
    }
};
