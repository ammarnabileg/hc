<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * هجرة **تنجح** لكنّها تفقد بيانات: تمسح صفوفًا بلا نقل ولا تحقّق.
 *
 * وهذا أخطر من هجرة تنفجر، لأنّها تمرّ بصمت. والتحقّق بعد كلّ خطوة (2.11-هـ)
 * هو ما يمسكها: أعداد الصفوف قبل/بعد لا تكذب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::table('app_version_history')->delete();
    }

    public function down(): void
    {
        // لا شيء — الاستعادة وحدها ترجّع ما مُسِح
    }
};
