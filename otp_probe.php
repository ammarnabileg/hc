<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r = Illuminate\Support\Facades\DB::table('security_otp_codes')->where('email',$argv[1])->orderByDesc('id')->first();
echo $r ? Illuminate\Support\Facades\Crypt::decryptString($r->code) : 'NONE';
