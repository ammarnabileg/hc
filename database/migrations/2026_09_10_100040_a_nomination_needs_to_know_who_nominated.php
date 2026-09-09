<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔧 تصحيح فجوة: `PublicBoardController::nominate()` كان يرفع الترشيح بلا
 * أثرٍ لصاحبه، فـ`LedgerBridge::notify()` كان يخاطب القائد نفسه بترشيحه هو
 * فقط — ولا إشعار حقيقيّ يعود إليه لحظة الاعتماد أو الرفض (23 — 1.8 · 8.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            $table->foreignId('nominated_by')->nullable()->after('is_public_board_candidate')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('nominated_by');
        });
    }
};
