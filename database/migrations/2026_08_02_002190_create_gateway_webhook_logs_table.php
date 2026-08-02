<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // سجلّ Webhook خام بكلّ نداء ونتيجة التحقّق من الهاش (19.5-ج-2)
        Schema::create('gateway_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('fawaterk');
            $table->string('invoice_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->json('headers')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('hash_valid')->default(false);
            $table->string('result', 32)->nullable(); // credited · duplicate · invalid_hash · unknown_invoice
            $table->timestamps();
        });
    }
};
