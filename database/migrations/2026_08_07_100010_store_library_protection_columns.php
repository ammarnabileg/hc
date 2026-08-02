<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شاشة الحماية في لوحة المكتبة (20.5): لكلّ منتج **تشغيل/إيقاف العلامة المائيّة**
 * و**صلاحيّة زمنيّة** — وفهرس (TOC) للقارئ المحميّ (20.3).
 *
 * لماذا أعمدة على المنتج لا إعدادات عامّة؟ لأنّ الدستور ينصّ صراحةً على أنّها
 * **«لكلّ منتج»** — والإعداد العامّ يبقى قيمةً افتراضيّةً حين لا يقرّر المنتج شيئًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'watermark_enabled')) {
                $table->boolean('watermark_enabled')->default(true);
            }

            if (! Schema::hasColumn('products', 'access_days')) {
                // صلاحيّة زمنيّة بالأيّام — و`null` تعني وصولًا دائمًا (20.1)
                $table->unsignedInteger('access_days')->nullable();
            }

            if (! Schema::hasColumn('products', 'toc')) {
                // فهرس الملفّ: [{"page":1,"title":"…"}] — يُحرَّر من شاشة الحماية
                $table->text('toc')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['watermark_enabled', 'access_days', 'toc']);
        });
    }
};
