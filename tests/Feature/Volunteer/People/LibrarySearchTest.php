<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\InternalLibraryItem;
use App\Models\Position;
use App\Services\Volunteer\People\LibrarySearch;

/**
 * المكتبة الداخليّة (23-3.3): البحث داخل المحتوى · المقتطف المظلَّل ·
 * والمقيَّد يظهر بقفل لا يُخفى.
 */
class LibrarySearchTest extends PeopleTestCase
{
    public function test_search_matches_text_inside_the_content_not_only_the_title(): void
    {
        $service = app(LibrarySearch::class);
        $viewer = $this->userWith(['internal_library.list', 'internal_library.view']);
        $entity = $this->makeEntity('قسم المكتبة');
        $this->makeMembership($viewer, $entity);

        InternalLibraryItem::create([
            'entity_id' => $entity->id,
            'title' => 'عنوان لا يحتوي الكلمة',
            'type' => 'document',
            'content_text' => 'هذه فقرة طويلة داخل المستند وفيها كلمة استوديو التصوير في منتصفها تمامًا.',
            'access_level' => 'entity',
            'approved_at' => now(),
        ]);

        $results = $service->search($viewer, ['q' => 'استوديو التصوير']);

        $this->assertCount(1, $results, 'البحث لازم يلقى الكلمة جوّه المحتوى نفسه لا في العنوان فقط.');
        $this->assertStringContainsString('<mark', (string) $results->first()->snippet);
        $this->assertStringContainsString('استوديو التصوير', (string) $results->first()->snippet);
    }

    public function test_restricted_item_is_shown_with_a_lock_not_hidden(): void
    {
        $service = app(LibrarySearch::class);
        $viewer = $this->userWith(['internal_library.list', 'library_access_requests.create'], 'ENTITY');
        $entity = $this->makeEntity('قسم مقيَّد');
        $this->makeMembership($viewer, $entity, 'coordinator');

        $item = InternalLibraryItem::create([
            'entity_id' => $entity->id,
            'title' => 'مستند مقيَّد ببوزشن فأعلى',
            'type' => 'document',
            'content_text' => 'محتوى إداريّ حسّاس.',
            'access_level' => 'restricted',
            'min_position_id' => Position::firstWhere('key', 'director')->id,
            'approved_at' => now(),
        ]);

        $results = $service->search($viewer, []);

        $found = $results->firstWhere('id', $item->id);

        $this->assertNotNull($found, 'المقيَّد لا يُخفى — يظهر بعنوانه.');
        $this->assertTrue((bool) $found->locked, 'ولازم يبان مقفولًا لمن لا يملك بوزشنه.');
        $this->assertFalse($service->canOpen($viewer, $item));
    }

    public function test_access_request_is_created_once_and_stays_pending(): void
    {
        $service = app(LibrarySearch::class);
        $viewer = $this->userWith(['internal_library.list', 'library_access_requests.create'], 'ENTITY');
        $entity = $this->makeEntity('قسم الطلبات');

        $item = InternalLibraryItem::create([
            'entity_id' => $entity->id,
            'title' => 'مستند خارج نطاقي',
            'type' => 'plan',
            'content_text' => 'خطّة داخليّة.',
            'access_level' => 'entity',
            'approved_at' => now(),
        ]);

        $service->requestAccess($item, $viewer, 'محتاجه لمهمّة');
        $service->requestAccess($item, $viewer, 'محتاجه لمهمّة');

        $this->assertDatabaseCount('library_access_requests', 1);
        $this->assertSame([$item->id], $service->pendingRequestIds($viewer, collect([$item])));
    }

    public function test_all_volunteers_level_is_open_to_everyone(): void
    {
        $service = app(LibrarySearch::class);
        $viewer = $this->userWith(['internal_library.list'], 'ENTITY');

        $item = InternalLibraryItem::create([
            'entity_id' => $this->makeEntity('قسم بعيد')->id,
            'title' => 'دليل مفتوح للجميع',
            'type' => 'document',
            'content_text' => 'محتوى مفتوح.',
            'access_level' => 'all_volunteers',
            'approved_at' => now(),
        ]);

        $this->assertTrue($service->canOpen($viewer, $item));
    }
}
