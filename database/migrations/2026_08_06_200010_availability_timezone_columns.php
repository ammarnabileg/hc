<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الإتاحة والتوقيت (الدستور 5).
 *
 * لماذا ثلاثة أعمدة لا عمود واحد؟ لأنّ الدستور يطلب أمرين معًا:
 * **كشفًا تلقائيًّا ديناميكيًّا** يتبع مكان المستخدم الآن، و**تعديلًا يدويًّا**
 * يحترمه النظام ولا يدهسه الكشف. فلو خُزّنا في خانة واحدة لضاع أحدهما:
 * إمّا أن يمسح الكشفُ اختيارَ المستخدم، أو أن يتجمّد التوقيت على أوّل قيمة.
 *
 * - `timezone`      : اختيار المستخدم اليدويّ — الأعلى أولويّةً ولا يُكتَب إلّا منه.
 * - `auto_timezone` : آخر منطقة زمنيّة مكتشَفة — تتحدّث كلّما تغيّر مكانه.
 * - `auto_timezone_at`: متى كان آخر كشف — لعرض «مكتشَف تلقائيًّا» ومنع الكتابة المتكرّرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('locale');
            }

            if (! Schema::hasColumn('users', 'auto_timezone')) {
                $table->string('auto_timezone', 64)->nullable()->after('timezone');
            }

            if (! Schema::hasColumn('users', 'auto_timezone_at')) {
                $table->timestamp('auto_timezone_at')->nullable()->after('auto_timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'auto_timezone', 'auto_timezone_at']);
        });
    }
};
