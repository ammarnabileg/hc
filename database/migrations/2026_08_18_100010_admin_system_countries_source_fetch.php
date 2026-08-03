<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 12.7-د «بيانات الدول» — **جلب المصدر عبر الشبكة + سجلّ الفحص**.
 *
 * لماذا جدولٌ مستقلّ للفحص ولا نكتب النتيجة على اللقطة نفسها؟ لأنّ **الفشل
 * لا يجوز أن يمسّ اللقطة الأخيرة الناجحة**: لو كتبنا «فشل» على اللقطة القائمة
 * ضاعت فروقٌ راجعها المالك ولم يقرّر فيها بعد، ولو أنشأنا لقطةً بحالة `failed`
 * لصارت هي «الأخيرة» فتختفي فروقُ الناجحة من الشاشة. فالفشل صفٌّ هنا، واللقطة
 * لا تُنشَأ أصلًا إلّا حين يصل جسمٌ سليم يمرّ على `import()` بكلّ تحقّقاته.
 *
 * و`snapshot_id` قابلٌ للفراغ لسببين: الفحص الفاشل بلا لقطة، والفحص الناجح
 * **بلا فروق** لا يُبقي لقطةً مكرّرة (سكوت: لا لقطة ولا إشعار).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('country_source_checks')) {
            return;
        }

        Schema::create('country_source_checks', function (Blueprint $table) {
            $table->id();

            // ok · failed — والحالة وحدها لا تكفي، فالسبب مكتوبٌ بالعربيّة تحتها
            $table->string('status', 16)->default('ok')->index();

            // no_url · timeout · http · body · shape — تصنيف الفشل ليُقاس ويُعرَض
            $table->string('failure', 24)->nullable();
            $table->text('message');

            $table->string('source_url', 512)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            // عدد المحاولات إعدادٌ يحرّره الأدمن — فالعمود يتّسع لقيمته مهما رفعها
            $table->unsignedSmallInteger('attempts')->default(1);

            // schedule · manual — مَن أطلق الفحص: المسحة الشهريّة أم زرّ الشاشة
            $table->string('trigger', 16)->default('manual');

            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->unsignedInteger('changed')->default(0);

            $table->foreignId('snapshot_id')->nullable()
                ->constrained('country_source_snapshots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }
};
