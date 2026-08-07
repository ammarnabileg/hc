@php
    /*
     | تاب الإدارة (12.1): **اعتماد/رفض الحساب من هنا مباشرة** (بالإضافة لصفحة
     | الاعتماد المخصّصة)، و**تعيين الدور/الأدوار** من صفحة الأدوار والصلاحيّات (12.2).
     |
     | ولماذا نفس مسار الاعتماد الجماعيّ بحقل `users[]` واحد؟ لأنّ قرار الاعتماد
     | له أثرٌ واحد لا اثنان (دور + هديّة + إشعار)، ومسارٌ ثانٍ يعني منطقًا يتفرّع
     | وينسى أحدُ فرعيه شيئًا مع أوّل تعديل.
     */
    $viewer = auth()->user();
    $pending = $user->status === 'pending';
@endphp

<div class="grid gap-4 lg:grid-cols-2">

    {{-- اعتماد/رفض الحساب --}}
    @if ($viewer->allows('user_approvals.approve') || $viewer->allows('user_approvals.reject'))
        <section class="card p-4">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <h3 class="font-bold text-sm">{{ setting('admin.users.partials.tab_admin.hala_alaatmad', 'حالة الاعتماد') }}</h3>
                <x-state-badge :state="$directory->statusState($user->status)"
                               :label="\App\Services\Admin\UserDirectory::statuses()[$user->status] ?? $user->status" />
            </div>

            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ setting('admin.approvals.free_note', 'التفعيل مجّانيّ باعتماد إداريّ — ولا رسوم على الباب') }}
            </p>

            @if (! $pending)
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_admin.alhsab_atraja_khlas_mafysh_qrar_aatmad_mstny', 'الحساب اتراجع خلاص — مافيش قرار اعتماد مستنّي.') }}</p>
            @else
                @if ($viewer->allows('user_approvals.approve'))
                    <form method="post" action="{{ route('admin.users.approve') }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="users[]" value="{{ $user->id }}">
                        <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                                style="min-block-size: 44px; background: var(--color-brand-500); color:#04201c">{{ setting('admin.users.partials.tab_admin.aatmad_alhsab', 'اعتماد الحساب') }}</button>
                    </form>
                @endif

                @if ($viewer->allows('user_approvals.reject'))
                    <form method="post" action="{{ route('admin.users.reject') }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="users[]" value="{{ $user->id }}">
                        <label class="block text-xs" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_admin.sbb_alrfd_hywsl_llmstkhdm', 'سبب الرفض (هيوصل للمستخدم)') }}</label>
                        <input list="reject-reasons" name="reason" required
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <datalist id="reject-reasons">
                            @foreach ($rejectReasons as $reason)
                                <option value="{{ $reason }}"></option>
                            @endforeach
                        </datalist>
                        <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                                style="min-block-size: 44px; background: var(--color-state-danger); color:#fff">{{ setting('admin.users.partials.tab_admin.rfd_alhsab', 'رفض الحساب') }}</button>
                    </form>
                @endif
            @endif
        </section>
    @endif

    {{-- الأدوار: العرض للجميع، والإسناد لمن يملك `roles.assign` وحده --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_admin.aladwar', 'الأدوار') }}</h3>
        <ul class="space-y-1 text-sm">
            @forelse ($user->roles as $role)
                <li class="flex items-center justify-between gap-2">
                    <span>{{ $role->name_ar }}</span>
                    <span class="text-xs" style="color: var(--text-muted)">
                        {{ $role->pivot->membership_id ? setting('admin.users.partials.tab_admin.dakhl_adwya', 'داخل عضويّة #').$role->pivot->membership_id : setting('admin.users.partials.tab_admin.dwr_mnsa', 'دور منصّة') }}
                    </span>
                </li>
            @empty
                <li style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_admin.mafysh_adwar_msnda', 'مافيش أدوار مسنَدة.') }}</li>
            @endforelse
        </ul>

        @if ($viewer->allows('roles.assign'))
            <a href="{{ route('admin.roles.assign', ['user' => $user->id]) }}"
               class="btn inline-flex items-center justify-center w-full mt-3 rounded-xl py-2 text-sm font-semibold motion-standard"
               style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                {{ setting('admin.users.partials.tab_admin.tayyn_dwr', 'تعيين دور') }}
            </a>
            <p class="text-xs mt-2" style="color: var(--text-muted)">
                {{ setting('admin.roles.assign_hint', 'الدور يحدّد «ماذا» والعضويّة تحدّد «أين» — فأدوار التطوّع تُسنَد داخل عضويّة.') }}
            </p>
        @endif

        {{-- الاستثناءات الفرديّة: صلاحيّة تُمنح أو تُمنع لهذا المستخدم بعينه فوق أدواره (12.2.2) --}}
        @if ($viewer->allows('permissions.assign'))
            <a href="{{ route('admin.permissions.assign', ['user' => $user->id]) }}"
               class="btn inline-flex items-center justify-center w-full mt-3 rounded-xl py-2 text-sm font-semibold motion-standard"
               style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                {{ setting('admin.users.partials.tab_admin.mnh_slahya_frdya', 'منح صلاحيّة فرديّة') }}
            </a>
        @endif
    </section>

    {{-- طلبات الإفادة المعلَّقة (9.1 · 24.5) --}}
    @if ($viewer->allows('user_attestation.manage'))
        <section class="card p-4 lg:col-span-2">
            <h3 class="font-bold text-sm mb-3">{{ setting('admin.users.partials.tab_admin.tlbat_alifada', 'طلبات الإفادة') }}</h3>

            @forelse ($pendingAttestations as $attestation)
                <div class="p-3 mb-2 rounded-xl" style="background: var(--surface-sunken)">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-sm font-semibold">{{ $attestation->from_name }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $attestation->created_at?->diffForHumans() }}</span>
                    </div>
                    @if ($attestation->body)
                        <p class="text-xs mb-2 whitespace-pre-line" style="color: var(--text-muted)">{{ $attestation->body }}</p>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="post" action="{{ route('admin.users.attestations.approve', [$user, $attestation]) }}">
                            @csrf
                            <button class="btn rounded-xl px-4 text-sm font-semibold motion-standard"
                                    style="min-height: 40px; background: var(--color-brand-500); color: #04201c">
                                {{ setting('admin.users.partials.tab_admin.aatmad', 'اعتماد') }}
                            </button>
                        </form>

                        <form method="post" action="{{ route('admin.users.attestations.reject', [$user, $attestation]) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input name="reason" required placeholder="{{ setting('admin.users.attestations.reject_reason_label', 'سبب الرفض') }}"
                                   class="rounded-xl px-3 text-sm" style="min-height: 40px; background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                            <button class="btn rounded-xl px-4 text-sm"
                                    style="min-height: 40px; background: var(--color-state-danger); color: #fff">
                                {{ setting('admin.users.partials.tab_admin.rfd', 'رفض') }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.users.partials.tab_admin.mafysh_tlbat_iafada_mstnya', 'مافيش طلبات إفادة مستنّية.') }}</p>
            @endforelse
        </section>
    @endif
</div>
