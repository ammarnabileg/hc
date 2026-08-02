<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قسم التوجيه 12.6-أ: ما وعد به الدستور ولم يكن له عمودٌ في القاعدة —
 * **استطلاع داخل المنشور** (عامّ النتيجة أو مخفيّها) · **جدولة متكرّرة** ·
 * **سلسلة Onboarding متدرّجة** للمستخدم الجديد.
 *
 * كلّ خطوة هنا Idempotent (2.11-د): نفحص وجود العمود قبل إضافته، والأعمدة
 * الجديدة لها قيمٌ افتراضيّة — فالتنصيبات القديمة تعمل فورًا بلا فقد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            // ---------------- استطلاع داخل المنشور (12.6-أ · 24.3)
            // `poll_results_public` هو الفارق بين «عامّ النتيجة» و«مخفيّها»،
            // والمخفيّ **لا تُرسَل نتيجته للمتصفّح أصلًا** قبل الإغلاق (2.9 — لا Dark Patterns).
            'poll_question' => fn (Blueprint $t) => $t->string('poll_question')->nullable()->after('cta_url'),
            'poll_options' => fn (Blueprint $t) => $t->json('poll_options')->nullable()->after('poll_question'),
            'poll_results_public' => fn (Blueprint $t) => $t->boolean('poll_results_public')->default(false)->after('poll_options'),
            'poll_closes_at' => fn (Blueprint $t) => $t->timestamp('poll_closes_at')->nullable()->after('poll_results_public'),

            // ---------------- الجدولة المتكرّرة (12.6-أ)
            'recurrence' => fn (Blueprint $t) => $t->string('recurrence', 16)->nullable()->after('scheduled_at'),
            'recurrence_until' => fn (Blueprint $t) => $t->timestamp('recurrence_until')->nullable()->after('recurrence'),
            'recurrence_last_at' => fn (Blueprint $t) => $t->timestamp('recurrence_last_at')->nullable()->after('recurrence_until'),
            'recurrence_parent_id' => fn (Blueprint $t) => $t->unsignedBigInteger('recurrence_parent_id')->nullable()->after('recurrence_last_at'),

            // ---------------- سلسلة Onboarding متدرّجة (12.6-أ)
            // الخطوة تظهر بعد مرور أيّامها على التسجيل **وبعد قراءة ما قبلها** — تسلسلٌ لا إغراق.
            'onboarding_step' => fn (Blueprint $t) => $t->unsignedInteger('onboarding_step')->nullable()->after('recurrence_parent_id'),
            'onboarding_delay_days' => fn (Blueprint $t) => $t->unsignedInteger('onboarding_delay_days')->default(0)->after('onboarding_step'),
        ];

        Schema::table('announcements', function (Blueprint $table) use ($columns) {
            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn('announcements', $name)) {
                    $definition($table);
                }
            }
        });

        if (! Schema::hasTable('announcement_poll_votes')) {
            Schema::create('announcement_poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('option_index');
                $table->timestamps();
                // صوتٌ واحد لكلّ مستخدم في كلّ استطلاع — والتبديل تعديلٌ لا صفٌّ جديد
                $table->unique(['announcement_id', 'user_id']);
            });
        }
    }
};
