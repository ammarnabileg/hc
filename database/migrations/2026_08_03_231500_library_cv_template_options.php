<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أثر القالب في المخرَج (الدستور 9): «الاستخراج النهائيّ PDF متوافق مع ATS،
 * ويُخصَم عدد تذاكر القالب» — فلا يجوز أن يُدفَع ثمنُ قالبٍ ولا يظهر أثره
 * في الملفّ المستخرَج. كان `/cv/ats.pdf` لا ينظر إلى القالب أصلًا.
 *
 * `ats_options`: تباعد وأحجام خطوط هذا القالب في مخرَج الـATS.
 * `description`: وصفٌ يظهر في شاشة إدارة القوالب للأدمن.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('cv_templates', 'ats_options')) {
                $table->json('ats_options')->nullable()->after('view_path');
            }

            if (! Schema::hasColumn('cv_templates', 'description')) {
                $table->string('description')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cv_templates', function (Blueprint $table) {
            $table->dropColumn(['ats_options', 'description']);
        });
    }
};
