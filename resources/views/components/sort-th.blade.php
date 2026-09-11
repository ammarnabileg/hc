@props(['key', 'label'])

@php
    /*
     | رأس عمودٍ قابلٍ للفرز في جداول لوحة الإدارة (12.5-أ · 12.6-أ · 12.6-ج).
     |
     | الفرز **على الخادم** بسلسلة الاستعلام (`sort` + `dir`) لا في المتصفّح،
     | لثلاثة أسباب: يحفظ الفلاتر والبحث كما هي · يعمل بلا جافاسكربت أصلًا ·
     | ويرتّب **كلّ** الصفوف لا صفحةَ الترقيم الظاهرة وحدها. وهو نفس نمط
     | الفلاتر القائم في هذه اللوحة (`x-filters` + `withQueryString()`).
     |
     | والنقرة الأولى تصعد، والثانية على العمود نفسه تهبط — و`page` تُسقَط
     | فلا يقع المستخدم على صفحةٍ فارغة بعد تغيير الترتيب.
     */
    $active = (string) request()->query('sort', '') === (string) $key;
    $currentDir = strtolower((string) request()->query('dir', '')) === 'asc' ? 'asc' : 'desc';
    $nextDir = $active && $currentDir === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]);
@endphp

<th {{ $attributes->merge(['class' => 'p-3 text-start']) }}
    data-sort-key="{{ $key }}"
    aria-sort="{{ $active ? ($currentDir === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a href="{{ $url }}" class="inline-flex items-center gap-1"
       title="{{ setting('ux.sort_th.title_1', 'رتّب بهذا العمود') }}">
        <span>{{ $label }}</span>
        {{-- المؤشّر رمزٌ لا لون: اللون لا يحمل المعنى وحده (2.16) --}}
        <span aria-hidden="true" class="text-xs"
              style="color: var(--text-muted)">{{ $active ? ($currentDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
    </a>
</th>
