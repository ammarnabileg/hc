<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موافقة صاحب الإفادة على نشرها ومفتاح إغلاقها (الدستور 9.1 · 10.0-ج).
 *
 * كان `/attestation/{code}` يفتح بـ`firstOrFail()` على **كود المستخدم** بلا أيّ
 * فحص موافقة، فيرى الزائر الاسم وXP والتدريبات والشهادات لمستخدمٍ لم يوافق —
 * والأكواد متسلسلة فالتعداد ممكن. وللـCV `is_public` صريح، فكان التناقض داخل
 * الميزة الواحدة. هنا نُسوّي الاثنين: موافقة صريحة + رابطٌ عشوائيّ غير قابل للتعداد.
 *
 * ولماذا على `cvs`: «خبراتي» صفٌّ واحد لكلّ مستخدم، والإفادة تابٌ داخله (9.1)،
 * فلا نُنشئ جدولًا لعلمٍ منطقيّ واحد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cvs', function (Blueprint $table) {
            if (! Schema::hasColumn('cvs', 'attestation_is_public')) {
                $table->boolean('attestation_is_public')->default(false);
            }

            if (! Schema::hasColumn('cvs', 'attestation_slug')) {
                $table->string('attestation_slug', 32)->nullable()->unique();
            }

            if (! Schema::hasColumn('cvs', 'attestation_views')) {
                $table->unsignedBigInteger('attestation_views')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cvs', function (Blueprint $table) {
            $table->dropColumn(['attestation_is_public', 'attestation_slug', 'attestation_views']);
        });
    }
};
