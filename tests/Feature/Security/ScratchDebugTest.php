<?php

namespace Tests\Feature\Security;

use App\Services\Security\OtpService;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ScratchDebugTest extends SecurityTestCase
{
    public function test_debug(): void
    {
        Mail::fake();

        $groups = setting('onboarding.identity.titles', []);
        $title = 'أستاذ';
        foreach (is_array($groups) ? $groups : [] as $titles) {
            foreach ((array) $titles as $one) {
                $title = (string) $one;
                break 2;
            }
        }

        $payload = [
            'title' => $title,
            'name_ar' => 'ياسمين طارق',
            'name_en' => 'Test User Name',
            'gender' => 'female',
            'address_line' => 'شارع التحرير',
            'email' => 'yasmin@test.local',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];

        $r = $this->post('/register', $payload);
        fwrite(STDERR, "\n=== register redirect: ".$r->headers->get('Location')."\n");
        fwrite(STDERR, 'pending keys: '.json_encode(array_keys((array) session(RequireVerifiedEmail::SESSION_PENDING)), JSON_UNESCAPED_UNICODE)."\n");
        fwrite(STDERR, 'pending: '.json_encode(session(RequireVerifiedEmail::SESSION_PENDING), JSON_UNESCAPED_UNICODE)."\n");

        $this->post(route('register.verify.send'));

        $row = DB::table('security_otp_codes')->where('email', 'yasmin@test.local')->where('purpose', OtpService::PURPOSE_REGISTER)->firstOrFail();
        $code = decrypt($row->code, false);

        $c = $this->post(route('register.verify.confirm'), ['code' => $code]);
        fwrite(STDERR, '=== confirm redirect: '.$c->headers->get('Location')."\n");
        $errors = session('errors');
        fwrite(STDERR, '=== errors type: '.get_debug_type($errors)."\n");
        fwrite(STDERR, '=== errors: '.json_encode($errors, JSON_UNESCAPED_UNICODE)."\n");

        $this->assertTrue(true);
    }
}
