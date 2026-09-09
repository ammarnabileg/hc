@extends('layouts.volunteer')

@section('title', setting('volunteer.file_invites.title', 'دعوة انضمام لملفّ'))

@section('content')
    <div class="max-w-md mx-auto">
        <div class="card p-6 text-center space-y-4">
            <h1 class="text-lg font-bold">{{ setting('volunteer.file_invites.heading', 'دعوة للانضمام') }}</h1>

            @if ($expired)
                <p class="text-sm" style="color: var(--color-state-danger)">{{ setting('volunteer.file_invites.expired', 'رابط الدعوة ده منتهي الصلاحيّة — كلّم اللي بعتهولك.') }}</p>
            @else
                <p class="text-sm">
                    {{ setting('volunteer.file_invites.body', 'إنت مدعوّ تنضمّ لملفّ') }}
                    <strong>{{ $link->entity?->name_ar }}</strong>
                    {{ setting('volunteer.file_invites.body_2', 'بوزشن') }}
                    <strong>{{ $link->position?->name_ar }}</strong>
                </p>

                @if ($errors->has('token'))
                    <p class="text-sm" style="color: var(--color-state-danger)">{{ $errors->first('token') }}</p>
                @endif

                <form method="post" action="{{ route('volunteer.file-invites.accept', $link->token) }}">
                    @csrf
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.file_invites.action', 'انضمّ دلوقتي') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
