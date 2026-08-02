<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سدّ فجوات دورة العمل (الدستور 23).
 *
 * لماذا هذه الأعمدة بالذات؟
 *  - `tasks.breakdown_due_at`: نافذة التفكيك (23-3.9-1) لا مكان لها في المخطّط،
 *    فبلا عمودٍ يحملها يبقى خصم −0.2/يوم قاعدةً بلا عدّاد.
 *  - `task_todos.user_id`: التودو «لا يظهر لأحد إلا صاحبه» (23-2.1) — وبلا صاحبٍ
 *    مكتوب يرث المالكُ الجديدُ قائمةَ السابق عند انتقال الملكيّة.
 *  - `task_types.*`: النوع «تشيك ليست + قيم مقترحة» بنصّ 23-0.3، والجدول بلا
 *    عمودٍ لأيٍّ منها.
 *  - `membership_absences.thawed_at`: تجميد الساعات في وضع «غائب» (23-6) يحتاج
 *    علامةً تمنع إزاحة المهل مرّتين — بنفس ميكانيزم الصيانة.
 *  - وتنظيف `escalations` من صفوف الاعتراض: مسارٌ قائم بذاته **لا يندرج ضمن
 *    الحالات التسع** (23-6)، ووجوده هناك كان يُسقِط الدورة كلّها عند السقف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // نافذة التفكيك: آخر موعد لتفكيك المهمّة وتوزيعها على مَن تحت (23-3.9-1)
            $table->timestamp('breakdown_due_at')->nullable()->after('deadline_at');
        });

        Schema::table('task_todos', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('task_id')->constrained('users')->cascadeOnDelete();
        });

        // الموجود يُنسَب لمالك مهمّته وقت الترحيل — لا تودو بلا صاحب
        DB::table('task_todos')->whereNull('user_id')->update([
            'user_id' => DB::raw('(select owner_id from tasks where tasks.id = task_todos.task_id)'),
        ]);

        Schema::table('task_types', function (Blueprint $table) {
            $table->json('checklist')->nullable()->after('default_deliverable_spec');
            $table->decimal('default_vxp', 10, 2)->nullable()->after('checklist');
            $table->string('default_priority', 16)->nullable()->after('default_vxp');
            $table->string('default_delivery_kind', 16)->nullable()->after('default_priority');
        });

        Schema::table('membership_absences', function (Blueprint $table) {
            $table->timestamp('thawed_at')->nullable()->after('reason');
        });

        // ⭐ الاعتراض خارج جدول الحالات التسع (23-6) — والصفوف القديمة تُزال
        DB::table('escalations')->where('case_type', 'objection')->delete();
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('breakdown_due_at'));

        Schema::table('task_todos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('task_types', fn (Blueprint $table) => $table->dropColumn([
            'checklist', 'default_vxp', 'default_priority', 'default_delivery_kind',
        ]));

        Schema::table('membership_absences', fn (Blueprint $table) => $table->dropColumn('thawed_at'));
    }
};
