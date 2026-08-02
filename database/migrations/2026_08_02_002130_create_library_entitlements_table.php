<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // مكتبتي: ما يملكه المستخدم بوصولٍ دائم (القسم 20)
        Schema::create('library_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('itemable');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 24)->default('purchase');
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'itemable_type', 'itemable_id'], 'entitlement_unique');
        });
    }
};
