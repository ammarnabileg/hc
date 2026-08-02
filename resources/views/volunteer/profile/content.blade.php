@php
    use App\Services\Volunteer\Profile\VolunteerProfileTabs;
@endphp

{{--
  محتوى طبقة التطوّع داخل البروفايل الواحد (10.0-أ · 13.4-م).
  الوسم `data-volunteer-profile` هو ما تتعرّف عليه قاعدةُ إظهار التاب المفتوح،
  فلا يظهر تابان في وقت واحد.
--}}
<div data-volunteer-profile>

    {{-- أزرار الهيدر: شكر · واتساب · سجلّ المشرف · «إجراءات» (13.4-م) --}}
    @include('volunteer.profile.partials.header-actions', ['header' => $header])

    @switch($active)
        @case(VolunteerProfileTabs::CONTACT)
            @include('volunteer.profile.tab-contact')
            @break
        @case(VolunteerProfileTabs::ORGANIZATION)
            @include('volunteer.profile.tab-organization')
            @break
        @case(VolunteerProfileTabs::PERFORMANCE)
            @include('volunteer.profile.tab-performance')
            @break
        @case(VolunteerProfileTabs::NOTES)
            @include('volunteer.profile.tab-notes')
            @break
        @case(VolunteerProfileTabs::OVERVIEW)
            @include('volunteer.profile.tab-overview')
            @break
    @endswitch
</div>

@push('scripts')
    <script>
        // أزرار الهيدر تنضمّ لكارت الهيدر نفسه — والمكان الافتراضيّ يبقى صالحًا لو الجافاسكربت متوقّف
        (() => {
            const bar = document.querySelector('[data-volunteer-header-actions]');
            const header = document.querySelector('main > header');
            if (bar && header) { header.appendChild(bar); }

            // دروب-داون «إجراءات» — فعل رئيسيّ واحد والباقي في قائمة (2.15-أ-2)
            document.addEventListener('click', (e) => {
                const toggle = e.target.closest('[data-actions-toggle]');
                const menu = document.querySelector('[data-actions-menu]');
                if (!menu) return;
                if (toggle) { menu.classList.toggle('hidden'); return; }
                if (!e.target.closest('[data-actions-menu]')) { menu.classList.add('hidden'); }
            });
        })();
    </script>
@endpush
