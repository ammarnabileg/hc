<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نادي الخامسة والسلاسل (الدستور 7.2 · 7.1).
 *
 * - `streak_days.xp_awarded`: كم XP مُنِح عن هذا اليوم — لأنّ سلّم الحضور
 *   المتدرّج يُمنَح **مرّةً واحدة لليوم**، والتخزين هو ما يمنع التكرار لا الواجهة.
 * - `streak_days.is_freeze`: اليوم مغطّى بدرع تجميد اشتراه المستخدم بتذكرة،
 *   فيَعبُر العدّ فوقه ولا تنكسر السلسلة (7.1-4 · 2.9).
 * - `streak_rewards`: تذكرة مكافأة السلسلة تُصرَف مرّةً لكلّ دورة مكتملة —
 *   والفريدة على (المستخدم، اليوم) هي ما يمنع الصرف المزدوج بإعادة الضغط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streak_days', function (Blueprint $table) {
            if (! Schema::hasColumn('streak_days', 'xp_awarded')) {
                $table->unsignedInteger('xp_awarded')->default(0)->after('club_5am');
            }

            if (! Schema::hasColumn('streak_days', 'is_freeze')) {
                $table->boolean('is_freeze')->default(false)->after('xp_awarded');
            }
        });

        if (! Schema::hasTable('streak_rewards')) {
            Schema::create('streak_rewards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // اليوم الذي اكتملت عنده الدورة — مفتاح منع التكرار
                $table->date('day');
                $table->unsignedInteger('streak_days_count')->default(0);
                $table->decimal('tickets', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'day']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_rewards');

        Schema::table('streak_days', function (Blueprint $table) {
            $table->dropColumn(['xp_awarded', 'is_freeze']);
        });
    }
};
