<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // وضع «غائب» والتفويض المؤقّت — يضيفه المشرف العام/مشرف المسار/الدايركتور (23)
        Schema::create('membership_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
