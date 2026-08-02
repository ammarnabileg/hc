<?php

use App\Models\LearningPath;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ثلاثة إصلاحات بنيويّة في مسارٍ واحد (12.4 · 12.6 · 12.4-أ):
 *
 * 1) **مسوّدة تحرير منفصلة عن المنشور** — الدستور يقول «حفظ تلقائيّ **كدرافت**»،
 *    وكان الحفظ التلقائيّ يكتب على السجلّ الحيّ فيغيّر تدريبًا يدرسه الناس الآن.
 * 2) **تجميع الإشعارات المتشابهة** (12.6-ب) — يحتاج مفتاح تجميع وعدّادًا،
 *    وإلّا صار «التجميع» عدّاد عرضٍ لا يدمج شيئًا.
 * 3) **سعر امتحان المسار مصدرٌ واحد** (12.4-أ) — كان له مصدران متعارضان فعلًا
 *    في البيانات (شاشة الأدمن 0 وشاشة المتدرّب 150)، وثالثٌ يتيم في الإعدادات.
 *    المصدر المعتمَد **صفّ الامتحان** لأنّه ما يُشحَن منه المتدرّب.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (1) مسوّدة تحرير التدريب — لقطة الفورم لا تمسّ الأعمدة الحيّة
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'draft_payload')) {
                $table->json('draft_payload')->nullable()->after('availability');
            }

            if (! Schema::hasColumn('courses', 'draft_saved_at')) {
                $table->timestamp('draft_saved_at')->nullable()->after('draft_payload');
            }
        });

        // (2) تجميع الإشعارات المتشابهة في إشعار واحد
        Schema::table('app_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('app_notifications', 'group_key')) {
                $table->string('group_key', 190)->nullable()->index()->after('category');
            }

            if (! Schema::hasColumn('app_notifications', 'group_count')) {
                $table->unsignedInteger('group_count')->default(1)->after('group_key');
            }
        });

        // كوبون الفعاليّة (12.11): «السعر (مجّانيّ/كوينز/تذكرة + كوبون/خصم)»
        // — الكوبون منصوصٌ عليه ولا عمود له، فلا سبيل لضبطه أصلًا
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('price_tickets');
            }
        });

        // (3) مزامنة سعر امتحان المسار — وإنشاء صفّ امتحان لكلّ مسار له سعر
        $this->syncPathExamPrices();

        // المصدر الثالث اليتيم: إعدادٌ عامّ لسعرٍ **لكلّ مسار** — يُشال فلا يعود
        // يُعرَض في لوحة الإعدادات كأنّه يفعل شيئًا (2.13)
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'learning.path.exam_price_coins')->delete();
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['draft_payload', 'draft_saved_at']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropColumn(['group_key', 'group_count']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('coupon_id');
        });
    }

    /**
     * التوفيق بلا فقد بيانات: القيمة غير الصفريّة تفوز، وعمود الأدمن يفوز عند
     * تساوي الطرفين في كونهما غير صفريّين — فلا يُلغى سعرٌ ساري المفعول بصمت
     * ولا يُخترَع سعرٌ لم يضبطه أحد.
     */
    private function syncPathExamPrices(): void
    {
        if (! Schema::hasTable('learning_paths') || ! Schema::hasTable('exams')) {
            return;
        }

        $morph = (new LearningPath)->getMorphClass();

        foreach (DB::table('learning_paths')->whereNull('deleted_at')->get() as $path) {
            $exam = DB::table('exams')
                ->where('examable_type', $morph)
                ->where('examable_id', $path->id)
                ->first();

            $adminPrice = (float) ($path->exam_price_coins ?? 0);
            $examPrice = (float) ($exam->price_coins ?? 0);
            $price = $adminPrice > 0 ? $adminPrice : $examPrice;

            if ($exam) {
                DB::table('exams')->where('id', $exam->id)->update([
                    'price_coins' => $price,
                    'updated_at' => now(),
                ]);
            } elseif ($price > 0) {
                DB::table('exams')->insert([
                    'examable_type' => $morph,
                    'examable_id' => $path->id,
                    'title_ar' => 'امتحان شهادة '.$path->name_ar,
                    'price_coins' => $price,
                    'pass_score' => 70,
                    'questions_count' => 20,
                    'duration_minutes' => 30,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // العمود يبقى مرآةً للعرض القديم، والمصدر المعتمَد صفّ الامتحان
            if ($adminPrice !== $price) {
                DB::table('learning_paths')->where('id', $path->id)->update([
                    'exam_price_coins' => $price,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
