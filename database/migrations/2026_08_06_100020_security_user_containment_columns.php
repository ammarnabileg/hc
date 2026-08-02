<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | أدوات احتواء الحساب المسيء (12.1): الحظر والتعليق المؤقّت كانا **تسميتَي
         | عرض** بلا أثر — فنضيف أعمدة الأثر: سبب ظاهر للمستخدم · نهاية التعليق ·
         | مَن نفّذ ومتى. وحذف الحساب Soft-delete والعمود موجود أصلًا (2.3).
         */
        Schema::table('users', function (Blueprint $table) {
            $table->string('containment_reason', 500)->nullable()->after('rejection_reason');
            $table->timestamp('suspended_until')->nullable()->after('containment_reason');
            $table->foreignId('contained_by')->nullable()->after('suspended_until')->constrained('users')->nullOnDelete();
            $table->timestamp('contained_at')->nullable()->after('contained_by');
            $table->timestamp('deletion_requested_at')->nullable()->after('contained_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contained_by');
            $table->dropColumn(['containment_reason', 'suspended_until', 'contained_at', 'deletion_requested_at']);
        });
    }
};
