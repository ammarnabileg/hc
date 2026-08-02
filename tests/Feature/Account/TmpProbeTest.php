<?php
namespace Tests\Feature\Account;
class TmpProbeTest extends AccountTestCase
{
    public function test_bad_field(): void
    {
        $u = $this->trainee();
        dump(get_class(app(\Illuminate\Contracts\Debug\ExceptionHandler::class)));
        $this->withExceptionHandling();
        $r = $this->actingAs($u)->patchJson(route('settings.field'), ['field' => 'status', 'value' => 'x']);
        dump($r->status());
    }
}
