@extends('layouts.admin')

@section('title', 'طلبات الاعتماد')

@section('content')
    <x-page-header title="طلبات الاعتماد"
                   :subtitle="setting('admin.approvals.free_note', 'التفعيل مجّانيّ باعتماد إداريّ — ولا رسوم على الباب')"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'المستخدمون', 'url' => route('admin.users.index')],
                       ['label' => 'طلبات الاعتماد'],
                   ]" />

    {{-- ثلاثة فلاتر ظاهرة + بحث (2.15-أ-4) --}}
    <x-filters :action="route('admin.users.approvals')">
        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">بحث بالاسم أو الكود أو البريد</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="اكتب اسم أو كود…"
                   class="rounded-xl px-3 py-2 text-sm w-64 max-w-full"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">عمر الطلب</span>
            <select name="age" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="late" @selected(request('age') === 'late')>متأخّر ({{ $lateDays }}+ يوم)</option>
            </select>
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">مصدر التسجيل</span>
            <select name="source" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="referral" @selected(request('source') === 'referral')>بدعوة</option>
            </select>
        </label>

        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">فلترة</button>
    </x-filters>

    @if ($pending->isEmpty())
        <x-empty :message="setting('admin.approvals.empty_message', 'مفيش طلبات معلّقة — كلّ حاجة تمام')"
                 action="رجوع للقيادة" :href="route('admin.dashboard')" />
    @else
        <div data-bulk-scope>
            <form method="post" action="{{ route('admin.users.approve') }}" id="approvals-form">
                @csrf

                {{-- ⭐ الإجراء الجماعيّ يظهر **عند الاختيار فقط** ومخفيّ تمامًا قبله (2.15-ب) --}}
                <div data-bulk-bar class="hidden card p-3 mb-3 flex flex-wrap items-center gap-3">
                    <span class="text-sm">محدَّد: <strong data-bulk-count>0</strong></span>

                    <button type="submit"
                            class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">اعتماد المحدَّد</button>

                    <button type="button" data-modal-open="reject-modal"
                            class="rounded-xl px-4 py-2 text-sm motion-standard"
                            style="background: var(--surface-sunken)">رفض المحدَّد</button>

                    <span class="text-xs" style="color: var(--text-muted)">الحدّ الأقصى للعمليّة الواحدة: {{ $bulkLimit }}</span>
                </div>

                <div class="card p-0 overflow-hidden hidden md:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background: var(--surface-sunken)">
                                <th class="px-4 py-3"><input type="checkbox" data-bulk-master aria-label="تحديد الكلّ"></th>
                                <th class="text-start px-4 py-3 font-semibold">المستخدم</th>
                                <th class="text-start px-4 py-3 font-semibold">البريد</th>
                                <th class="text-start px-4 py-3 font-semibold">الداعي</th>
                                <th class="text-start px-4 py-3 font-semibold">عمر الطلب</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $account)
                                @php
                                    $age = $account->created_at?->diffInDays(now()) ?? 0;
                                    $state = $age >= $lateDays ? 'danger' : 'warn';
                                @endphp
                                <tr class="border-t" style="border-color: var(--border)">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="users[]" value="{{ $account->id }}" data-bulk-item
                                               aria-label="تحديد {{ $account->shortName() }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.users.show', $account) }}" class="inline-flex items-center gap-2 hover:underline">
                                            <x-avatar :user="$account" size="7" />
                                            <span>{{ $account->shortName() }}</span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">{{ $directory->mask($account->email, 'email') }}</td>
                                    <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">
                                        {{ $referrals->get($account->id)?->referrer?->shortName() ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-state-badge :state="$state" :label="$account->created_at?->diffForHumans()" />
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        {{-- الأفعال السريعة داخل الصفّ بلا بوب-أب (2.15-ب) --}}
                                        <button type="submit" name="users[]" value="{{ $account->id }}"
                                                class="text-xs hover:underline" style="color: var(--color-brand-500)">اعتماد</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
                <div class="md:hidden space-y-3">
                    @foreach ($pending as $account)
                        <div class="card p-3 flex items-center gap-3">
                            <input type="checkbox" name="users[]" value="{{ $account->id }}" data-bulk-item
                                   aria-label="تحديد {{ $account->shortName() }}">
                            <x-avatar :user="$account" size="9" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.users.show', $account) }}" class="block truncate font-semibold">{{ $account->shortName() }}</a>
                                <div class="text-xs" style="color: var(--text-muted)">{{ $account->created_at?->diffForHumans() }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </form>

            {{-- الرفض بسبب واضح — والرسالة تشرح ولا تعاتب (2.17-ج) --}}
            <x-modal id="reject-modal" title="رفض الحسابات المحدَّدة">
                <form method="post" action="{{ route('admin.users.reject') }}" class="space-y-3"
                      onsubmit="document.querySelectorAll('#approvals-form [data-bulk-item]:checked').forEach((box) => {
                          const clone = document.createElement('input');
                          clone.type = 'hidden'; clone.name = 'users[]'; clone.value = box.value;
                          this.appendChild(clone);
                      })">
                    @csrf

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">سبب الرفض (هيتبعت للمستخدم)</span>
                        <select name="reason" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-state-danger); color: #fff">تأكيد الرفض</button>
                </form>
            </x-modal>
        </div>

        <div class="mt-4">{{ $pending->links() }}</div>
    @endif
@endsection
