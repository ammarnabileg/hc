<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // كلّ قيم Rep إعدادات قابلة للتعديل (13.4-ن)
        Schema::create('rep_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique(); // task.early · meeting.within_3h · academy.path_complete …
            $table->string('group', 32)->index(); // tasks · meetings · academy · leadership · behavior
            $table->string('label_ar');
            $table->decimal('value', 5, 2);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
