<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * لقب السفير (7.6.1 · 2.9-8): **ألقاب فقط بلا شارات** — يُمنَح آليًّا
     * عند بلوغ عدد الدعوات المفعَّلة عتبةً من `setting('ambassadors.tiers')`.
     * ويُخزَّن على المستخدم نفسه ليظهر في البروفايل وبطاقة العضو بلا استعلام إضافيّ.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ambassador_tier')) {
                $table->string('ambassador_tier', 24)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'ambassador_title')) {
                $table->string('ambassador_title', 64)->nullable();
            }
            if (! Schema::hasColumn('users', 'ambassador_invites')) {
                $table->unsignedInteger('ambassador_invites')->default(0);
            }
            if (! Schema::hasColumn('users', 'ambassador_granted_at')) {
                $table->timestamp('ambassador_granted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ambassador_tier', 'ambassador_title', 'ambassador_invites', 'ambassador_granted_at']);
        });
    }
};
