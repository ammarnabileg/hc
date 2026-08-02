<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التسعير متعدّد العملات في المتجر (17) وتسعير عناصر البندل (18).
 *
 * لماذا عمود `price_currency` صريح ولا نستنتج العملة من «أيّ عمودٍ أكبر من صفر»؟
 * لأنّ الاستنتاج يجعل منتجًا بسعرين (كوينز وتذاكر) غامضًا، وقد كان العمود
 * `price_tickets` موجودًا **ويُتجاهَل بصمت** فيُسلَّم المنتج مجّانًا — والعلاج
 * أن تكون العملة قرارًا معلَنًا يراه الأدمن ويحكم الخصم في الخادم.
 *
 * و`bundle_items.price_coins` هو **Override سعر العنصر داخل الباقة** (18):
 * `null` تعني «السعر الطبيعيّ للعنصر»، وهي القيمة الافتراضيّة في الإنبوت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // عملة السعر: coins · tickets · xp (17)
            $table->string('price_currency', 16)->default('coins')->after('price_tickets');
            $table->decimal('price_xp', 12, 2)->default(0)->after('price_tickets');
        });

        Schema::table('bundle_items', function (Blueprint $table) {
            // null = السعر الطبيعيّ للعنصر، ورقم = Override محصور في صفحة البندل (18)
            $table->decimal('price_coins', 12, 2)->nullable()->after('itemable_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_currency', 'price_xp']);
        });

        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropColumn('price_coins');
        });
    }
};
