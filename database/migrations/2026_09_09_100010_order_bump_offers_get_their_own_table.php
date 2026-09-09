<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * عروض Order-bump (17) — كانت صفًّا واحدًا من نوع `json` (`store.order_bump.offers`)
 * تُحرَّر بـ`<textarea>` خامٍ في شاشة الإعدادات، رغم أنّ المصفوفة (12.2.2) تنصّ
 * على ثلاث صلاحيّات منفصلة `order_bump.create/edit/delete` — والصلاحيّة الفرديّة
 * على **فعلٍ** تفترض موردًا فرديًّا يقع عليه الفعل، لا صفًّا واحدًا يُستبدَل كلّه.
 *
 * فيصير كلّ عرضٍ صفًّا مستقلًّا يُنشأ ويُعدَّل ويُحذَف بمفرده من شاشة إدارةٍ حقيقيّة
 * (بدل نصّ JSON حرّ لا يتحقّق من صحّة العنصر ولا يمنع خطأ إملائيّ يكسر الشاشة).
 *
 * القيمة الحاليّة في `settings.value` تُنقَل صفًّا صفًّا — فلا يفقد المالك تهيئته.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_bump_offers', function (Blueprint $table): void {
            $table->id();
            $table->string('parent_type', 32);
            $table->string('parent_slug', 190);
            $table->string('bump_type', 32);
            $table->string('bump_slug', 190);
            $table->decimal('price_coins', 10, 2)->nullable();
            $table->text('teaser')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // الاستعلام الوحيد الذي يقرأ الجدول: عروض عنصرٍ بعينه (PricingService::bumpOffers)
            $table->index(['parent_type', 'parent_slug']);
        });

        $this->migrateExistingOffers();

        // ⛔ المفتاح القديم مات بعد نقل قيمته — بقاؤه صفًّا محروقًا يظنّه أحدهم مصدرًا (2.13-د)
        $deadId = DB::table('settings')->where('key', 'store.order_bump.offers')->value('id');

        if ($deadId) {
            DB::table('setting_overrides')->where('setting_id', $deadId)->delete();
            DB::table('settings')->where('id', $deadId)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_bump_offers');
    }

    /** الصفّ القديم كان JSON واحدًا — يُفكّ إلى صفوف الجدول الجديد بلا فقدٍ */
    private function migrateExistingOffers(): void
    {
        $raw = DB::table('settings')->where('key', 'store.order_bump.offers')->value('value');
        $rows = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

        if (! is_array($rows) || $rows === []) {
            return;
        }

        $now = now();

        DB::table('order_bump_offers')->insert(array_map(fn (array $row) => [
            'parent_type' => (string) ($row['parent_type'] ?? ''),
            'parent_slug' => (string) ($row['parent_slug'] ?? ''),
            'bump_type' => (string) ($row['bump_type'] ?? ''),
            'bump_slug' => (string) ($row['bump_slug'] ?? ''),
            'price_coins' => isset($row['price_coins']) ? (float) $row['price_coins'] : null,
            'teaser' => $row['teaser'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }
};
