<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دليل المستخدم (12.6-ج): «محرّر لكلّ دليل يقبل إرفاق ملفّات بكلّ الأنواع» —
     * ولم يكن على `help_articles` أيّ عمود مرفقٍ أصلًا. عمودٌ واحد يكفي لأنّ
     * منتقي المكتبة نفسه (12.4-هـ) يقبل أيّ نوع ملفّ — صورة/فيديو/PDF —
     * بنفس اسم/نمط `media_path` الموجود على `announcements`.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('help_articles', 'media_path')) {
            Schema::table('help_articles', function (Blueprint $table) {
                $table->string('media_path')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('help_articles', 'media_path')) {
            Schema::table('help_articles', function (Blueprint $table) {
                $table->dropColumn('media_path');
            });
        }
    }
};
