<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجلّ النسخ الاحتياطيّة (12.7-و): الحجم · التاريخ · مَن أخذها · سلامتها.
        // لماذا صفّ لكلّ نسخة؟ لأنّ ملفًّا على القرص بلا سجلّ لا يُعرَف مَن أخذه ولا هل هو سليم.
        Schema::create('backup_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('kind', 16)->default('full');   // full · database · files
            $table->string('status', 16)->default('done'); // done · failed · missing
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('checksum', 64)->nullable();    // sha256 — للتحقّق من السلامة قبل أيّ استرجاع
            $table->boolean('is_scheduled')->default(false);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_files');
    }
};
