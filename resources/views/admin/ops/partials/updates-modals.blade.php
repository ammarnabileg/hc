@can('updates.manage')
    @push('modals')
        {{-- ⛔ تأكيد مزدوج: عبارة يكتبها الأدمن بيده + إقرار صريح — ولا تنفيذ قبلهما --}}
        <x-modal id="migrate-confirm" title="تأكيد تنفيذ الترحيل">
            <form method="post" action="{{ route('admin.ops.updates.migrate') }}" class="space-y-3">
                @csrf

                <p class="text-sm">
                    هيتنفّذ <strong>{{ count($pending) }}</strong> هجرة على قاعدة البيانات الحيّة.
                    @if (setting('updates.backup_before_migrate', true))
                        وهناخد نسخة احتياطيّة قبلها تلقائيًّا.
                    @endif
                </p>

                @if ($pending !== [])
                    <ul class="rounded-xl p-3 max-h-40 overflow-y-auto" style="background: var(--surface-sunken)">
                        @foreach ($pending as $name)
                            <li class="font-mono text-xs break-all py-1">{{ $name }}</li>
                        @endforeach
                    </ul>
                @endif

                <x-form.input name="confirm" label="اكتب «{{ $confirmPhrase }}» للتأكيد"
                              :hint="'الكتابة اليدويّة مقصودة — عشان مافيش ضغطة بالغلط.'" autocomplete="off" />

                <label class="flex items-start gap-2 text-sm" style="min-height: 44px">
                    <input type="checkbox" name="understood" value="1" class="mt-1">
                    <span>فاهم إنّ العمليّة دي بتغيّر قاعدة البيانات، ومسجَّلة باسمي في سجلّ التدقيق.</span>
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">نفّذ دلوقتي</button>
            </form>
        </x-modal>
    @endpush
@endcan

@can('updates.restore')
    @push('modals')
        <x-modal id="rollback-confirm" title="تأكيد الاسترجاع">
            <form method="post" action="{{ route('admin.ops.updates.rollback') }}" class="space-y-3">
                @csrf

                <div class="rounded-xl p-3 text-sm"
                     style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent); color: var(--color-state-danger)">
                    ◉ الاسترجاع بيشيل آخر دفعة هجرات — <strong>والبيانات اللي في جداولها هتتمسح ولا ترجع</strong>.
                </div>

                @if ($lastBatch !== [])
                    <ul class="rounded-xl p-3 max-h-40 overflow-y-auto" style="background: var(--surface-sunken)">
                        @foreach ($lastBatch as $name)
                            <li class="font-mono text-xs break-all py-1">{{ $name }}</li>
                        @endforeach
                    </ul>
                @endif

                <label class="block">
                    <span class="block text-sm mb-1">اكتب «{{ $rollbackPhrase }}» للتأكيد</span>
                    <input type="text" name="confirm" id="rollback-confirm-phrase" autocomplete="off"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="flex items-start gap-2 text-sm" style="min-height: 44px">
                    <input type="checkbox" name="understood" value="1" class="mt-1">
                    <span>فاهم إنّ اللي هيتشال مش هيرجع، وإنّي مسؤول عن القرار ده.</span>
                </label>

                <button class="w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--surface-raised); color: var(--color-state-danger)">استرجع آخر دفعة</button>
            </form>
        </x-modal>
    @endpush
@endcan
