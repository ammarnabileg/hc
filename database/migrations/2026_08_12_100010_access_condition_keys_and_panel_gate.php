<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تفعيل طبقة الشروط، وإسقاط الصلاحيّة المسمّاة باسم شاشة (12.2.1-أ · 12.2.1-ج).
 *
 * `condition_key` يبقى كما هو: **نصّ عربيّ للعرض** (هو ما يقرأه المسؤول في الشاشة).
 * ويُضاف بجانبه `condition_keys` — مفاتيحُ من القائمة المقفولة **تُقيَّم وقت الطلب**.
 * فصلُ النصّ عن المفتاح مقصود: العرض يبقى عربيًّا، والتقييم يبقى مقفولًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->json('condition_keys')->nullable()->after('condition_key');
        });

        // ⭐ `admin_panel.view` صلاحيّةٌ باسم شاشة — ممنوعة بنصّ 12.2.1-أ.
        // الباب صار يُحسَب من الصلاحيّات نفسها (App\Support\Access\AdminPanelSurface).
        $id = DB::table('permissions')->where('key', 'admin_panel.view')->value('id');

        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permission_user')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('condition_keys');
        });
    }
};
