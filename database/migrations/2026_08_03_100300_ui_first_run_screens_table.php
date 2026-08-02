<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * «شاشة أوّل مرّة» (2.15-د) — على أهمّ الشاشات فقط منعًا للزحام.
         * نسجّل من رآها ومتى، لأنّ زرّ «؟» يعيدها وقت ما شاء المستخدم،
         * وللأدمن «إعادة العرض للجميع» فيُمسَح السجلّ لا الإعداد.
         */
        Schema::create('user_first_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('screen', 96)->index();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'screen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_first_runs');
    }
};
