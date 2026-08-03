<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| أعمدة **رحلة بناء الهدف** — المرحلة صفر (الدستور 23 — 1.1 … 1.4).
|
| لماذا هذه الأعمدة بالذات؟
|  - **1.1:** «سبب الهدف» حقلٌ منصوصٌ في الفورم، و**معيار التحقّق** له صورتان:
|    رقم من X إلى Y (`target_from/to` موجودان) **أو حالة تُفحَص بنعم/لا** — ولم
|    يكن لنصّ الحالة عمودٌ أصلًا، فكان «هدف بلا معيار تحقّق لا يُحفَظ» غير قابل
|    للفرض على النوع البوليانيّ. ومعه `goal_tracks`: **الهدف لا يراه أحد حتى
|    يُربَط بمسار**، فالربط علاقةٌ لا عمودٌ واحد («بمسار أو أكثر»).
|  - **1.2 · 1.3:** `build_stage` يقول أين تقف الرحلة، و`work_packages.build_status`
|    يقول أيّ حزمةٍ رفعها دايركتورها للمراجعة — فالتجميع يعرف ما وصله.
|  - **1.4:** `edit_holder` هو **القفل الطبقيّ** نفسه: حيازة التحرير لطبقةٍ واحدة
|    (مشرف المسار ⟵ ثمّ القمّة بعد «رفع معاينة»)، ومَن سلَّم صار قارئًا فقط.
|    و`goal_field_revisions` هو سجلّ **«تمّ التعديل»**: صفٌّ لكلّ إنبوت مُعدَّل
|    (مَن · متى · ماذا كان) — لأنّ السؤال في الشاشة عن **حقلٍ بعينه** لا عن
|    السجلّ كلّه، فجدول التدقيق العامّ لا يجيبه بلا حفرٍ في JSON.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            if (! Schema::hasColumn('goals', 'reason')) {
                $table->text('reason')->nullable()->after('description');
            }
            if (! Schema::hasColumn('goals', 'verification_statement')) {
                // نصّ الحالة القابلة للفحص بنعم/لا — بديل المدى الرقميّ لا زينة
                $table->string('verification_statement')->nullable()->after('verification_type');
            }
            if (! Schema::hasColumn('goals', 'build_stage')) {
                // draft · linked · breakdown · filling · aggregation · preview · executed
                $table->string('build_stage', 24)->default('draft')->after('status')->index();
            }
            if (! Schema::hasColumn('goals', 'edit_holder')) {
                // top · track — القفل الطبقيّ، ومَن ليس حائزًا فهو قارئ فقط
                $table->string('edit_holder', 16)->nullable()->after('build_stage');
            }
            if (! Schema::hasColumn('goals', 'preview_raised_at')) {
                $table->timestamp('preview_raised_at')->nullable()->after('edit_holder');
            }
            if (! Schema::hasColumn('goals', 'preview_raised_by')) {
                $table->foreignId('preview_raised_by')->nullable()->after('preview_raised_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('milestones', function (Blueprint $table) {
            if (! Schema::hasColumn('milestones', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('sort_order')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('work_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('work_packages', 'build_status')) {
                // filling (عند الدايركتور) · submitted (رُفِعت للمراجعة)
                $table->string('build_status', 24)->default('filling')->after('progress_percent');
            }
            if (! Schema::hasColumn('work_packages', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('build_status');
            }
            if (! Schema::hasColumn('work_packages', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // «الهدف يظهر لحظة ربطه بمسار أو أكثر» — والإشعار لمشرفي تلك المسارات وحدهم
        if (! Schema::hasTable('goal_tracks')) {
            Schema::create('goal_tracks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
                $table->foreignId('track_id')->constrained()->cascadeOnDelete();
                $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['goal_id', 'track_id']);
            });
        }

        // سجلّ «تمّ التعديل» — لكلّ حقلٍ بعينه: مَن · متى · ماذا كان
        if (! Schema::hasTable('goal_field_revisions')) {
            Schema::create('goal_field_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
                $table->string('subject_type', 32);  // goal · milestone · package · task
                $table->unsignedBigInteger('subject_id');
                $table->string('field', 48);
                $table->string('label')->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id', 'field']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_field_revisions');
        Schema::dropIfExists('goal_tracks');

        Schema::table('work_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['build_status', 'submitted_at']);
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preview_raised_by');
            $table->dropColumn(['reason', 'verification_statement', 'build_stage', 'edit_holder', 'preview_raised_at']);
        });
    }
};
