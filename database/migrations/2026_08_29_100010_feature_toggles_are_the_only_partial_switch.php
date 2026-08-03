<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🖥️ مفاتيح المزايا (24.3) — **البديل الوحيد للصيانة الجزئيّة الملغاة** (12.7-و).
 *
 * ولمّا كانت الصيانة الجزئيّة لميزةٍ بعينها **مرفوضةً بنصّ 2.x**، فإنّ غياب هذا
 * الجدول يعني أنّ المنصّة كلّها **بلا بابٍ واحد** لإطفاء ميزة: لا الصيانة تفعلها
 * (عامّة فقط) ولا شيء غيرها. فهذه الهجرة هي ذلك الباب.
 *
 * ⚠️ مؤرَّخة **بعد** آخر هجرة في الشجرة (2026_08_28_100010) عن قصد — فالهجرة
 * المؤرَّخة قبل ما هو مطبَّق لا تُشغَّل على قاعدةٍ قائمة فيبطل أثرها صامتةً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();

            // نمط المفتاح نفسه المعتمَد للإعدادات: `المجال.الميزة` (2.13-و)
            $table->string('key', 96)->unique();

            // المجموعات التسع المنصوصة في 24.3 (تدريب · تلعيب · حروب · متجر
            // وماليّات · مكتبة · تطوّع · فعاليّات · توجيه · بروفايل)
            $table->string('group', 32)->index();

            // اللافتة العربيّة في **القاعدة** لا في الكود — فهي قيمةٌ يعدّلها
            // المالك، وحرقُها في صنفٍ PHP مخالفةُ 2.13 يمسكها `settings:hardcoded`.
            $table->string('label_ar');
            $table->string('label_en')->nullable();

            $table->boolean('enabled')->default(true);

            // «شارة تجريبيّة للمزايا الجديدة» — العَلَم على الميزة، وإظهاره إعداد
            $table->boolean('is_beta')->default(false);

            // ⭐ «مَن يراها أثناء الإيقاف»: none (لا أحد) · admins (الأدمن فقط)
            // · roles (أدوار محدّدة). ويُفرَض على **الخادم** لا بإخفاء زرّ.
            $table->string('visibility', 16)->default('none');
            $table->json('visible_roles')->nullable();

            // سلوك الميزة الموقوفة لهذه الميزة وحدها: فاضٍ = يرث الإعداد العامّ
            // `features.disabled_behavior` (إخفاء كامل ⇄ إظهار رسالة).
            $table->string('behavior', 16)->default('');

            // **نصّ ما يراه المستخدم بدلها** (ع/إ) — من بوب-أب الإيقاف
            $table->text('message_ar')->nullable();
            $table->text('message_en')->nullable();

            $table->boolean('notify_affected')->default(false);

            // سبب الإيقاف — يدخل الـAudit، ويبقى هنا ليُقرَأ من الجدول مباشرةً
            $table->text('disabled_reason')->nullable();
            $table->timestamp('disabled_at')->nullable();

            // تنبيه الأدمن عند إيقاف ميزة > N ساعة — يُرسَل مرّةً لا كلّ دقيقة
            $table->timestamp('long_outage_alerted_at')->nullable();

            $table->foreignId('last_toggled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_toggled_at')->nullable();

            $table->timestamps();
        });

        /*
        | ⭐ **النطاق** (عامّ ⇄ Override لدور/شريحة).
        | صفٌّ واحد لكلّ (ميزة × نوع نطاق × هدف)، و`enabled` فيه قرارٌ صريح
        | (تشغيل أو إيقاف) لا مجرّد استثناء — فالـOverride يعمل في الاتّجاهين:
        | يفتح ميزةً موقوفةً لدورٍ مجرِّب، ويقفلها عن شريحةٍ بعينها وهي شغّالة.
        */
        Schema::create('feature_flag_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_flag_id')->constrained()->cascadeOnDelete();

            // role = دور (roles.id) · segment = شريحة جمهور (ad_audiences.id)
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id');

            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['feature_flag_id', 'scope_type', 'scope_id'], 'feature_flag_overrides_unique');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
        Schema::dropIfExists('feature_flags');
    }
};
