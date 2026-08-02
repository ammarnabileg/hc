<?php
namespace Tests\Feature\Volunteer\Core;
class DbgTest extends VolunteerCoreTestCase {
  public function test_dbg(): void {
    $user = $this->makeUser();
    $entity = $this->makeEntity();
    $this->makeMembership($user, $entity);
    $this->grant($user, ['tasks.edit','tasks.view','tasks.list']);
    $task = $this->makeTask($user, $entity, ['deadline_at' => now()->addDay()]);
    $r = $this->actingAs($user)->post(route('volunteer.tasks.deliver', $task), ['body' => 'المخرج جاهز']);
    fwrite(STDERR, "STATUS: ".$r->getStatusCode()."\n");
    fwrite(STDERR, substr($r->getContent(),0,800)."\n");
    $this->assertTrue(true);
  }
}
