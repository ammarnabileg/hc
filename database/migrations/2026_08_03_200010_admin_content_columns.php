<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أعمدة مجال «إدارة التدريب والشهادات والتوجيه» (12.4 · 12.5 · 12.6 · 24.1 · 24.3).
     *
     * لماذا مايجريشن جديد؟ لأنّ المخطّط الأصليّ مقفول ولا يُمسّ (دليل البناء §1)،
     * والشاشات المعتمَدة تحتاج حقولًا لم تكن في المخطّط: اسم الشهادة على التدريب،
     * سعر امتحان شهادة المسار، ربط أنواع الشهادات بأعمدة قاعدة البيانات، وغيرها.
     * وكلّ إضافة محميّة بـhasColumn حتى لا تتصادم مع مجالٍ آخر أضاف نفس العمود.
     */
    public function up(): void
    {
        // ----------------------------------------------- المسارات (12.4-أ)
        $this->add('learning_paths', [
            // سعر امتحان شهادة المسار الكامل بالكوينز — لكلّ مسار على حدة
            'exam_price_coins' => fn (Blueprint $t) => $t->decimal('exam_price_coins', 12, 2)->default(0),
        ]);

        // ----------------------------------------------- التدريبات (12.4-ب)
        $this->add('courses', [
            // اسم الشهادة غير اسم العرض — عمود مستقلّ بلغتين
            'cert_name_ar' => fn (Blueprint $t) => $t->string('cert_name_ar')->nullable(),
            'cert_name_en' => fn (Blueprint $t) => $t->string('cert_name_en')->nullable(),
            'free_first_time' => fn (Blueprint $t) => $t->boolean('free_first_time')->default(false),
            'paywall_text_ar' => fn (Blueprint $t) => $t->text('paywall_text_ar')->nullable(),
            'paywall_text_en' => fn (Blueprint $t) => $t->text('paywall_text_en')->nullable(),
            // الإتاحة: فترات متعدّدة + أوقات تشغيل يوميّة (12.4-ب — تاب الإتاحة)
            'availability' => fn (Blueprint $t) => $t->json('availability')->nullable(),
            'max_lesson_xp' => fn (Blueprint $t) => $t->unsignedInteger('max_lesson_xp')->nullable(),
        ]);

        // ----------------------------------------------- الامتحانات
        $this->add('exams', [
            'questions_count' => fn (Blueprint $t) => $t->unsignedInteger('questions_count')->default(0),
        ]);

        // ----------------------------------------------- الدروس (12.4-ج)
        $this->add('lessons', [
            // كود/HTML تابع للفيديو — منفصل عن نصّ الدرس
            'embed_html' => fn (Blueprint $t) => $t->longText('embed_html')->nullable(),
        ]);

        // ----------------------------------------------- مكتبة الوسائط (12.4-د)
        $this->add('media_items', [
            'folder' => fn (Blueprint $t) => $t->string('folder', 64)->nullable()->index(),
        ]);

        // ----------------------------------------------- الاعتمادات (12.5-أ)
        $this->add('certificate_accreditations', [
            // نصّ «معتمدة من …» كما يظهر في صفحة التحقّق
            'verify_note_ar' => fn (Blueprint $t) => $t->string('verify_note_ar')->nullable(),
            'verify_note_en' => fn (Blueprint $t) => $t->string('verify_note_en')->nullable(),
        ]);

        // ----------------------------------------------- أنواع الشهادات (12.5-ب)
        $this->add('certificate_types', [
            'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
            // الربط بقاعدة البيانات بلا حدود: [{key,table,column,label}]
            'bindings' => fn (Blueprint $t) => $t->json('bindings')->nullable(),
            'numbering_padding' => fn (Blueprint $t) => $t->unsignedTinyInteger('numbering_padding')->nullable(),
            'auto_issue_event' => fn (Blueprint $t) => $t->string('auto_issue_event', 64)->nullable(),
            'stamp_path' => fn (Blueprint $t) => $t->string('stamp_path')->nullable(),
            'signature_path' => fn (Blueprint $t) => $t->string('signature_path')->nullable(),
        ]);

        // ----------------------------------------------- التعليمات (12.6-أ)
        $this->add('announcements', [
            'type' => fn (Blueprint $t) => $t->string('type', 32)->nullable()->index(),
            'acknowledge_tickets' => fn (Blueprint $t) => $t->unsignedInteger('acknowledge_tickets')->default(0),
        ]);

        // ----------------------------------------------- دليل المستخدم (12.6-ج)
        $this->add('help_articles', [
            'title_en' => fn (Blueprint $t) => $t->string('title_en')->nullable(),
            'views' => fn (Blueprint $t) => $t->unsignedInteger('views')->default(0),
        ]);

        // ----------------------------------------------- الشكاوى (24.3)
        $this->add('complaints', [
            'wants_contact' => fn (Blueprint $t) => $t->boolean('wants_contact')->default(false),
            'contact_channel' => fn (Blueprint $t) => $t->string('contact_channel', 32)->nullable(),
            'close_reason' => fn (Blueprint $t) => $t->string('close_reason')->nullable(),
        ]);
    }

    public function down(): void
    {
        $map = [
            'learning_paths' => ['exam_price_coins'],
            'courses' => ['cert_name_ar', 'cert_name_en', 'free_first_time', 'paywall_text_ar', 'paywall_text_en', 'availability', 'max_lesson_xp'],
            'exams' => ['questions_count'],
            'lessons' => ['embed_html'],
            'media_items' => ['folder'],
            'certificate_accreditations' => ['verify_note_ar', 'verify_note_en'],
            'certificate_types' => ['description', 'bindings', 'numbering_padding', 'auto_issue_event', 'stamp_path', 'signature_path'],
            'announcements' => ['type', 'acknowledge_tickets'],
            'help_articles' => ['title_en', 'views'],
            'complaints' => ['wants_contact', 'contact_channel', 'close_reason'],
        ];

        foreach ($map as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }

    /** @param  array<string, callable(Blueprint):mixed>  $columns */
    private function add(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn($table, $name)) {
                    $definition($blueprint);
                }
            }
        });
    }
};
