<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * شاشة «الرسائل الإيجابيّة» منصوصةٌ جدولًا بعمود **اللغة** (2.6-ب) —
     * والعمود لم يوجد أصلًا. والحقل القديم `body_ar` كان يفترض عربيّةً
     * دائمًا، فلو صار مضمونه إنجليزيًّا لرسالةٍ بلغتها `en` لكذب اسمه على
     * القارئ — فأُعيد تسميته `body` عامًّا (مطابقةً لـ`image_templates.name`
     * مع `language` بجانبه، السابقة المعتمَدة في هذا الكود نفسه) لا يُبقى
     * عمودًا مضلِّلًا.
     */
    public function up(): void
    {
        Schema::table('positive_messages', function (Blueprint $table) {
            $table->renameColumn('body_ar', 'body');
            $table->string('language', 5)->default('ar')->after('context');
        });
    }

    public function down(): void
    {
        Schema::table('positive_messages', function (Blueprint $table) {
            $table->renameColumn('body', 'body_ar');
            $table->dropColumn('language');
        });
    }
};
