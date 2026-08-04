{{-- تاب «تفاصيل»: الأرقام التي خرجت من صفّ الـKPI التزامًا بحدّ الأربعة (2.15-أ-3) --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-kpi :label="setting('dashboard.details.completed_label', 'تدريبات مكتملة')" :value="$details['completed']" icon="check"
           :hint="setting('dashboard.details.completed_hint', 'خلّصتها بالكامل')" />
    <x-kpi :label="setting('dashboard.details.active_label', 'تدريبات جارية')" :value="$details['active']" icon="library"
           :hint="setting('dashboard.details.active_hint', 'لسّه شغّال فيها')" />
    <x-kpi :label="setting('dashboard.details.rank_label', 'ترتيبك في الليدر بورد')" :value="$details['rank']" icon="trophy"
           :hint="str_replace(':peers', number_format($details['peers']), (string) setting('dashboard.details.rank_hint', 'من بين :peers متدرّبًا'))" />
</div>

<section class="card p-5 mt-6">
    <h2 class="font-bold text-sm">{{ setting('dashboard.details.title', 'تفصيل تقدّمك') }}</h2>

    <dl class="mt-3 grid gap-3 sm:grid-cols-3 text-sm">
        <div>
            <dt style="color: var(--text-muted)" class="text-xs">{{ setting('dashboard.details.lessons_done', 'دروس مكتملة') }}</dt>
            <dd class="font-bold" data-count-to="{{ $details['lessons_done'] }}">{{ number_format($details['lessons_done']) }}</dd>
        </div>
        <div>
            <dt style="color: var(--text-muted)" class="text-xs">{{ setting('dashboard.details.lessons_total', 'إجمالي دروس تدريباتك') }}</dt>
            <dd class="font-bold">{{ number_format($details['lessons_total']) }}</dd>
        </div>
        <div>
            <dt style="color: var(--text-muted)" class="text-xs">{{ setting('dashboard.details.xp_from_courses', 'XP من التدريبات') }}</dt>
            <dd class="font-bold">{{ number_format($details['xp_from_courses']) }}</dd>
        </div>
    </dl>
</section>
