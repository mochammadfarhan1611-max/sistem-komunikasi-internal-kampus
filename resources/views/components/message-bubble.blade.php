@props([
    'message',
    'isOwn' => false,
])

@php
    $waktu = $message->created_at->timezone('Asia/Jakarta')->format('H:i').' WIB';
@endphp

<div
    class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}"
    data-message-id="{{ $message->id }}"
>
    <div class="max-w-[82%] rounded-2xl px-4 py-2.5 shadow-sm sm:max-w-[70%] lg:max-w-xl {{ $isOwn ? 'rounded-br-sm bg-blue-600 text-white' : 'rounded-bl-sm border border-slate-200/80 bg-white text-slate-800' }}">
        <p class="text-sm leading-relaxed whitespace-pre-line break-words" data-body>{{ $message->body }}</p>
        <div class="mt-1 flex items-center justify-end gap-1 {{ $isOwn ? 'text-blue-100' : 'text-slate-400' }}">
            <span class="text-[11px]" data-time>{{ $waktu }}</span>
            @if($isOwn)
                <span
                    data-read-icon
                    class="material-symbols-outlined text-[15px] leading-none {{ $message->is_read ? 'text-emerald-300' : 'text-blue-200' }}"
                    title="{{ $message->is_read ? 'Sudah dibaca' : 'Terkirim' }}"
                >{{ $message->is_read ? 'done_all' : 'done' }}</span>
            @endif
        </div>
    </div>
</div>
