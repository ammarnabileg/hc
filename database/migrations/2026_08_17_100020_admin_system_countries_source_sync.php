<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 12.7-د «بيانات الدول»: تحديث المصدر مع **فحص فروق النسخة قبل الدمج بلا فقد**.
 *
 * لماذا نسخةٌ مخزَّنة (Snapshot) لا سحبٌ مباشر عند كلّ فحص؟ لأنّ 2.11 تشترط أن
 * **تُعرَض الفروق للمالك ليقرّر قبل التنفيذ** — وقرارٌ يُبنى على نسخةٍ متغيّرة
 * تحت اليد ليس قرارًا. فالنسخة تُثبَّت أوّلًا، ثمّ يُفحَص عليها، ثمّ يُدمَج منها.
 *
 * و`sync_hidden_at` تفصل «أخفاه الدمج لأنّه اختفى من المصدر» عن «أخفاه الأدمن
 * بيده» — فالاسترجاع ممكن، ولا يُحسَب إخفاءُ الأدمن أثرًا للمصدر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('country_source_snapshots')) {
            Schema::create('country_source_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('source', 32)->default('dr5hn');
                $table->string('version', 64)->nullable();
                $table->json('payload');
                $table->json('summary')->nullable();
                $table->json('report')->nullable();
                $table->string('status', 24)->default('pending')->index(); // pending · checked · merged
                $table->timestamp('checked_at')->nullable();
                $table->timestamp('merged_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('countries', 'sync_hidden_at')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->timestamp('sync_hidden_at')->nullable()->after('is_active');
            });
        }

        if (! Schema::hasColumn('governorates', 'sync_hidden_at')) {
            Schema::table('governorates', function (Blueprint $table) {
                $table->timestamp('sync_hidden_at')->nullable()->after('is_active');
            });
        }
    }
};
