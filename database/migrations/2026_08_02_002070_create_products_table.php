<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('type', 32)->default('digital'); // digital · protected_pdf · cv_template
            $table->string('file_path')->nullable();
            $table->boolean('is_downloadable')->default(true);
            $table->unsignedInteger('teaser_pages')->default(0);
            $table->decimal('price_coins', 12, 2)->default(0);
            $table->decimal('price_tickets', 12, 2)->default(0);
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_indexable')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
