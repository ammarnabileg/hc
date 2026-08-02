<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('task_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained()->nullOnDelete(); // إلزاميّ منطقيًّا
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete(); // صب-تاسك
            $table->foreignId('blocked_by_task_id')->nullable()->constrained('tasks')->nullOnDelete();

            $table->text('brief')->nullable();
            $table->text('deliverable_spec')->nullable();
            $table->decimal('vxp_value', 12, 2)->default(0);
            $table->unsignedTinyInteger('priority')->default(2);

            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('merge_window_at')->nullable(); // نافذة الدمج للأب
            $table->timestamp('delivered_at')->nullable();    // الساعة تقف لحظة التسليم
            $table->timestamp('approved_at')->nullable();

            // قيد التنفيذ · متعثّرة · مُسلَّمة/قيد المراجعة · معتمدة · مُرجَعة · عدم تسليم · مُغلَقة
            $table->string('status', 32)->default('in_progress')->index();
            $table->string('source', 24)->default('assigned'); // assigned · public_board · recurring
            $table->unsignedTinyInteger('return_count')->default(0);
            $table->unsignedTinyInteger('blocked_count')->default(0);
            $table->unsignedTinyInteger('extension_count')->default(0);
            $table->boolean('late_due_to_child')->default(false); // علم «متأخّر بسبب…»
            $table->string('batch_status', 24)->nullable();      // مسودّة · بانتظار مراجعة الأبلاين · معتمدة
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'status']);
            $table->index(['entity_id', 'status']);
        });
    }
};
