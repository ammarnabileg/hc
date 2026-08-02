<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * توحيد مفتاح أسباب الشكوى (الدستور 11).
 *
 * كان فورم المستخدم يقرأ `account.complaints.categories` ولوحة الأدمن تقرأ
 * `complaints.reasons` — مفتاحان لمعنًى واحد، فتحرير الأدمن بلا أثر على
 * القائمة التي يراها المستخدم فعلًا.
 *
 * والترحيل **بلا يتامى**: القيمة القديمة تُنقَل إن كانت هي المكتوبة، ثمّ يُحذَف
 * المفتاح القديم — فلا يبقى صفٌّ لا يقرؤه أحد ويوهم المالك أنّه يضبط شيئًا.
 */
return new class extends Migration
{
    private const OLD = 'account.complaints.categories';

    private const NEW = 'complaints.reasons';

    public function up(): void
    {
        $old = DB::table('settings')->where('key', self::OLD)->first();

        if ($old === null) {
            return;
        }

        $new = DB::table('settings')->where('key', self::NEW)->first();

        // القديم يعلو فقط إن كان مضبوطًا فعلًا وخالف افتراضيّه — وإلّا فالجديد كما هو
        $customised = filled($old->value) && $old->value !== $old->default_value;

        if ($customised) {
            if ($new === null) {
                DB::table('settings')->insert([
                    'key' => self::NEW,
                    'group' => 'complaints',
                    'label_ar' => 'أسباب الشكاوى والمقترحات',
                    'type' => 'json',
                    'value' => $old->value,
                    'default_value' => $old->default_value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('settings')->where('key', self::NEW)->update([
                    'value' => $old->value,
                    'updated_at' => now(),
                ]);
            }
        }

        // لا يتيم يبقى: تجاوزات الكيانات تتبع المفتاح، والمفتاح القديم يُحذَف
        $oldId = (int) $old->id;

        if ($newId = DB::table('settings')->where('key', self::NEW)->value('id')) {
            DB::table('setting_overrides')->where('setting_id', $oldId)->update(['setting_id' => $newId]);
        } else {
            DB::table('setting_overrides')->where('setting_id', $oldId)->delete();
        }

        DB::table('settings')->where('id', $oldId)->delete();

        Cache::forget('settings');
    }

    public function down(): void
    {
        // لا رجعة: المفتاح الموحَّد هو الصحيح، وإعادة الازدواج تُعيد العطل نفسه.
    }
};
