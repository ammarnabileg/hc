<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| أعمدة مجال «المشاريع والأهداف والأداء» — مايجريشن جديد لا يمسّ القديم (دليل البناء 1).
|
| لماذا هذه الأعمدة بالذات؟
|  - إعلان تحقّق معيار المَعلَم بدليل مرفق ثمّ اعتماده خلال نافذة (23 — 1.7).
|  - فرق النسخة وحقّ الاعتراض 24 ساعة على نسخة الاعتماد، والسكوت قبول (23 — 1.6).
|  - تناوب البنود المتكرّرة بالموازن وعدّاد الفائتة (23 — 1.8).
|  - أثر توزيع VXP فوق الوعاء من الرصيد الشخصيّ بموافقة صريحة (23 — 3.9-٥).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            if (! Schema::hasColumn('milestones', 'verification_status')) {
                // pending · declared (بانتظار مشرف المسار) · approved · rejected
                $table->string('verification_status', 24)->default('pending')->after('is_verified');
            }
            if (! Schema::hasColumn('milestones', 'declared_by')) {
                $table->foreignId('declared_by')->nullable()->after('verification_status')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('milestones', 'declared_at')) {
                $table->timestamp('declared_at')->nullable()->after('declared_by');
            }
            if (! Schema::hasColumn('milestones', 'evidence_note')) {
                $table->text('evidence_note')->nullable()->after('declared_at');
            }
            if (! Schema::hasColumn('milestones', 'approval_due_at')) {
                // نافذة اعتماد مشرف المسار (افتراضيّ 24 ساعة — إعداد)
                $table->timestamp('approval_due_at')->nullable()->after('evidence_note');
            }
        });

        Schema::table('work_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('work_packages', 'submitted_snapshot')) {
                $table->json('submitted_snapshot')->nullable()->after('progress_percent');
            }
            if (! Schema::hasColumn('work_packages', 'approved_snapshot')) {
                $table->json('approved_snapshot')->nullable()->after('submitted_snapshot');
            }
            if (! Schema::hasColumn('work_packages', 'objection_due_at')) {
                $table->timestamp('objection_due_at')->nullable()->after('approved_snapshot');
            }
            if (! Schema::hasColumn('work_packages', 'objection_status')) {
                // open · raised · accepted_by_silence — والسكوت قبول (23 — 1.6)
                $table->string('objection_status', 24)->nullable()->after('objection_due_at');
            }
            if (! Schema::hasColumn('work_packages', 'objection_note')) {
                $table->text('objection_note')->nullable()->after('objection_status');
            }
            if (! Schema::hasColumn('work_packages', 'objection_at')) {
                $table->timestamp('objection_at')->nullable()->after('objection_note');
            }
        });

        Schema::table('work_items', function (Blueprint $table) {
            if (! Schema::hasColumn('work_items', 'rotation_pool')) {
                // معرّفات الدور في التناوب الموزون — والموازن يختار الأقلّ حملًا منهم
                $table->json('rotation_pool')->nullable()->after('assigned_user_id');
            }
            if (! Schema::hasColumn('work_items', 'generated_count')) {
                $table->unsignedInteger('generated_count')->default(0)->after('next_generation_at');
            }
            if (! Schema::hasColumn('work_items', 'missed_count')) {
                $table->unsignedInteger('missed_count')->default(0)->after('generated_count');
            }
            if (! Schema::hasColumn('work_items', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_public_board_candidate');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'assigned_by_balancer')) {
                // «وُجِّه إليك لأنّك الأقلّ حملًا حاليًّا» — يُعرَض للمُسنَد إليه
                $table->boolean('assigned_by_balancer')->default(false)->after('source');
            }
            if (! Schema::hasColumn('tasks', 'personal_vxp_top_up')) {
                // الزيادة فوق وعاء الأب لا تأتي إلّا من رصيده الشخصيّ بموافقته الصريحة
                $table->decimal('personal_vxp_top_up', 12, 2)->default(0)->after('vxp_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('declared_by');
            $table->dropColumn(['verification_status', 'declared_at', 'evidence_note', 'approval_due_at']);
        });

        Schema::table('work_packages', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_snapshot', 'approved_snapshot',
                'objection_due_at', 'objection_status', 'objection_note', 'objection_at',
            ]);
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn(['rotation_pool', 'generated_count', 'missed_count', 'is_archived']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['assigned_by_balancer', 'personal_vxp_top_up']);
        });
    }
};
