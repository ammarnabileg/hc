<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدرجة الأولى من سلّم العتبات: **الإنذار عند −8** (23-0.2 — إجراء عتبات الهبوط، البند 1).
 *
 * النصّ حرفيًّا: «**عند تخطّي −8 (الإنذار):** يظهر المؤشّر الأحمر · إشعار **لكلّ
 * أبلايناته النشطين** عبر عضويّاته · التزام التواصل الموثَّق خلال **48 ساعة** يقع
 * على **أبلاين العضويّة التي وقعت فيها المعاملة الكاسرة لحاجز −8** · ويُسجَّل
 * التواصل في الملاحظات الإداريّة بالبروفايل».
 *
 * ولماذا **جدول** لا مجرّد مؤشّرٍ محسوب؟ لأنّ ثلاثة من أربعة في النصّ ليست عرضًا
 * بل **واقعة لها زمن وصاحب**: إشعارٌ وقع لأشخاصٍ بأعيانهم · **التزامٌ بموعد**
 * (48 ساعة) على شخصٍ بعينه · وتواصلٌ **موثَّق أو غير موثَّق**. والرقم الظاهر
 * يتحرّك ويتصفّر شهريًّا، فلو قيس الالتزام منه لَما بقي منه أثرٌ يُسأل عنه أحد.
 *
 * وأصرح دليلٍ على أنّ الصفّ مطلوبٌ بذاته أنّ النصّ نفسه يستدعيه لاحقًا في ملفّ
 * لجنة التحقيق (23-0.2-4-لجنة-4): «**توثيق تواصل الإنذار عند −8 (حدث أم لا —
 * وهو ما يحاسب الأبلاين أيضًا)**». فمن غير هذا الصفّ لا تملك اللجنة جوابًا على
 * سؤالها الأوّل، ولا يُحاسَب الأبلاين على شيء.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_rep_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // العتبة ورصيده لحظتها — لا يُقرآن من الإعدادات لاحقًا: الإعداد يتغيّر
            // والحادثة لا تتغيّر (نفس مبدأ صفّ البتر).
            $table->decimal('threshold', 6, 2);
            $table->decimal('rep_at_warning', 6, 2);

            // ⭐ **المعاملة الكاسرة** وكيانها — عليهما وحدهما يتحدّد **مَن يقع عليه
            // الالتزام**: «أبلاين العضويّة التي وقعت فيها المعاملة الكاسرة».
            $table->unsignedBigInteger('breaking_transaction_id')->nullable()->index();
            $table->unsignedBigInteger('breaking_entity_id')->nullable()->index();
            $table->foreignId('responsible_upline_id')->nullable()->constrained('users')->nullOnDelete();

            // «إشعار لكلّ أبلايناته النشطين عبر عضويّاته» — العدد ومَن هم بأعيانهم
            $table->unsignedInteger('uplines_notified')->default(0);
            $table->json('uplines')->nullable();

            // «التزام التواصل الموثَّق خلال 48 ساعة»
            $table->timestamp('contact_due_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->foreignId('contacted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('contact_note_id')->nullable(); // الملاحظة الإداريّة نفسها
            $table->timestamp('breached_at')->nullable();              // فات الموعد بلا توثيق

            // إنذارٌ واحد لكلّ دورة — والدورة تنتهي بالتصفير الشهريّ (13.4-ن-ز)
            $table->timestamp('cycle_ends_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'cycle_ends_at']);
            $table->index(['contact_due_at', 'contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_rep_warnings');
    }
};
