<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تعليقات الفيديو (الدستور 3.1): تحت كلّ فيديو قسم تعليقات، لكلّ تعليق لايك وردّ،
     * والإشراف يخفي أو يحذف بصلاحيّة.
     *
     * لماذا `parent_id` بمستوًى واحد؟ لأنّ 3.1 ينصّ على «ردّ» على التعليق لا على شجرة
     * بلا قاع — والشجرة العميقة تكسر القراءة على الموبايل (2.15-ج).
     *
     * ولماذا `likes_count` مخزَّن ومعه جدول لايكات؟ لأنّ العرض التدريجيّ (6 كلّ مرّة)
     * يقرأ العدّاد بلا عدٍّ متكرّر، والجدول يمنع تكرار اللايك بقيدٍ فريد في القاعدة
     * لا بشرطٍ في الكود.
     */
    public function up(): void
    {
        Schema::create('video_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('video_comments')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->boolean('is_hidden')->default(false);          // إخفاء إشرافيّ — لا مسح
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();                                  // الحذف قابل للاسترجاع (12.2)

            $table->index(['lesson_id', 'parent_id', 'id']);
        });

        Schema::create('video_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_comment_id')->constrained('video_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['video_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_comment_likes');
        Schema::dropIfExists('video_comments');
    }
};
