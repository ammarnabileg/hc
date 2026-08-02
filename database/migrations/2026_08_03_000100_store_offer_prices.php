<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سعر العرض بتاريخ انتهاء (16): «سعر أساسيّ + سعر عرض صالح حتى تاريخٍ محدَّد».
     * بدونه لا يمكن عرض شارة خصم **بقيمتها الحقيقيّة** في المتجر (2.9 · 21.1-د).
     * مايجريشن مضاف لا يمسّ عمودًا قائمًا، ومحميّ بـhasColumn حتى لا يتصادم.
     */
    public function up(): void
    {
        foreach (['products', 'courses'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'offer_price_coins')) {
                    $blueprint->decimal('offer_price_coins', 12, 2)->nullable();
                }

                if (! Schema::hasColumn($table, 'offer_ends_at')) {
                    $blueprint->timestamp('offer_ends_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'courses'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['offer_price_coins', 'offer_ends_at'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
