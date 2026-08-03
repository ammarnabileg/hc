<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شرائح الجمهور (12.13): النوع **ديناميكيّة/ثابتة** ودورة حياة الشريحة.
 *
 * والفرق بين النوعين **حقيقيّ في السلوك لا تسمية**: الثابتة تحتفظ بقائمة أعضائها
 * لحظة التجميد في `audience_segment_members` فلا يتغيّر عددها بتغيّر البيانات،
 * والديناميكيّة تُعاد حسبتها من الشرط في كلّ مرّة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_audiences', function (Blueprint $table) {
            $table->string('segment_type', 16)->default('dynamic')->after('kind'); // dynamic · static
            $table->text('description')->nullable()->after('name');
            $table->timestamp('frozen_at')->nullable()->after('last_built_at');
            // الأرشفة بدل الحذف — Toggle من الإعدادات (12.13)
            $table->timestamp('archived_at')->nullable()->after('frozen_at');
            $table->foreignId('created_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('audience_segment_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ad_audience_id', 'user_id'], 'audience_segment_members_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_segment_members');

        Schema::table('ad_audiences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['segment_type', 'description', 'frozen_at', 'archived_at']);
        });
    }
};
