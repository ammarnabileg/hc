<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | رموز التحقّق (2.5-ب · 2.3): تحقّق البريد عند التسجيل · تأكيد حذف الحساب ·
         | استرجاع كلمة السرّ برمز.
         |
         | ⚠️ الدستور يفرض أنّ **OTP التسجيل لا يتغيّر أبدًا لنفس البريد** — وهو ضعف
         | أمنيّ معروف ومنصوص عليه، فنُخفّفه بثلاثة قيود: الرمز **مشفَّر** في القاعدة
         | لا نصًّا صريحًا · عدّاد محاولات يقفل بعد الحدّ · إعادة الإرسال بعد مهلة.
         | أمّا رموز الحذف والاسترجاع فمؤقّتة وتُجدَّد في كلّ طلب.
         */
        Schema::create('security_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('purpose', 32)->index(); // register_email · account_delete · password_reset
            $table->text('code');                   // مشفَّر — لا يُخزَّن الرمز صريحًا أبدًا
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // فارغ = دائم (رمز التسجيل)
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_otp_codes');
    }
};
