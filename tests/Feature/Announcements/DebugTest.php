<?php
namespace Tests\Feature\Announcements;
use App\Models\{User, Entity, Membership, Position, Track};
use App\Services\Notifications\Notifier;
use Database\Seeders\{CoreSeeder, RoleSeeder, AnnouncementDemoSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugTest extends TestCase
{
    use RefreshDatabase;
    public function test_debug(): void
    {
        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AnnouncementDemoSeeder::class);
        $user = User::create(['name'=>'v','email'=>'v@t.local','password'=>'secret-password','code'=>'VVV111','status'=>'active']);
        $entity = Entity::create(['track_id'=>Track::where('key','department')->value('id'),'name_ar'=>'كيان','status'=>'active']);
        Membership::create(['user_id'=>$user->id,'entity_id'=>$entity->id,'position_id'=>Position::where('key','coordinator')->value('id'),'is_primary'=>true,'started_at'=>now(),'status'=>'active']);
        Notifier::send($user, 'system', 'عنوان منصّة');
        Notifier::send($user, 'task', 'عنوان تطوّع', null, null, 'volunteer');
        dump($user->fresh()->isVolunteer());
        dump($user->notificationsFeed()->pluck('layer','title')->all());
        $html = $this->actingAs($user)->get(route('notifications.index', ['tab'=>'volunteer']))->getContent();
        dump(substr_count($html,'عنوان تطوّع'), substr_count($html,'عنوان منصّة'), substr_count($html,'التطوّع'));
        $this->assertTrue(true);
    }
}
