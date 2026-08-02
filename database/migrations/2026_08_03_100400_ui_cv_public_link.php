<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cvs', function (Blueprint $table) {
            // رابط CV عامّ قابل للمشاركة — زيّ صفحة الشهادة (9)
            $table->string('public_slug', 32)->nullable()->unique()->after('cv_template_id');
            $table->boolean('is_public')->default(false)->after('public_slug');
            $table->unsignedInteger('public_views')->default(0)->after('is_public');
            // لغة العرض المختارة للرابط العامّ والاستخراج (9 — ثنائيّة اللغة بصفر تكلفة)
            $table->string('export_locale', 5)->default('ar')->after('public_views');
        });
    }

    public function down(): void
    {
        Schema::table('cvs', function (Blueprint $table) {
            $table->dropColumn(['public_slug', 'is_public', 'public_views', 'export_locale']);
        });
    }
};
