<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('ad_audiences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 24)->default('retargeting'); // retargeting · lookalike_source
            $table->json('rule')->nullable();       // شرط الشريحة من بياناتنا
            $table->unsignedInteger('ttl_days')->default(30);
            $table->unsignedInteger('refresh_hours')->default(24);
            $table->timestamp('last_built_at')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
