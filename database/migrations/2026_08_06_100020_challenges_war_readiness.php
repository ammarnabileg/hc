<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الاستعداد (15.0).
     *
     * `user_id` **فريد** لأنّ الاستعداد حصريّ: لا يصحّ أن يكون المستخدم
     * مستعدًّا لأكثر من نوع حرب في نفس اللحظة — والقيد في القاعدة لا في الكود
     * حتى لا يُخترَق بطلبين متزامنين.
     */
    public function up(): void
    {
        Schema::create('war_readiness', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('war_type', 24)->index();
            $table->timestamp('ready_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('war_readiness');
    }
};
