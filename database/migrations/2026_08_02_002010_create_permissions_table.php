<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // Capability-Based: الصلاحيّة تُسمّى «المورد.الفعل» — لا باسم دور ولا شاشة
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // volunteers.approve
            $table->string('resource', 64)->index();
            $table->string('action', 32)->index();     // 13 فعلًا قياسيًّا
            $table->string('group', 64)->index();      // المجموعات الثمانية
            $table->string('label_ar');
            $table->text('description')->nullable();
            $table->json('allowed_scopes')->nullable(); // SELF·TEAM·SUBTREE·ENTITY·TRACK·ALL
            $table->string('condition_key', 64)->nullable(); // من القائمة المقفولة
            $table->boolean('is_sensitive')->default(false); // 🔒
            $table->boolean('is_owner_only')->default(false); // عزل الماليّ لمالك المنصّة
            $table->timestamps();
        });
    }
};
