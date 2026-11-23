@props([
    'message',
    'isOwn' => false,
    'isGroup' => false,
])

@php
    $waktu = $message->created_at->timezone('Asia/Jakarta')->format('H:i').' WIB';
    $senderName = $message->sender?->nama_lengkap
        ?? $message->sender_name
        ?? ($isOwn ? (auth()->user()?->nama_lengkap ?? 'Anda') : ($message->sender_identifier ?? 'Pengguna'));
    $cleanSnippet = \Illuminate\Support\Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $message->body)), 140);
    $replyTo = $message->reply_to_payload ?? null;
@endphp

<div
    class="group flex items-center gap-1.5 {{ $isOwn ? 'justify-end' : 'justify-start' }}"
    data-message-id="{{ $message->id }}"
    data-sender-name="{{ $senderName }}"
    data-message-body="{{ $cleanSnippet }}"
>
    @if($isOwn)
        <button
            type="button"
            data-reply-btn
            title="Balas pesan"
            aria-label="Balas pesan"
            class="flex h-7 w-7 flex-shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 opacity-0 transition-all hover:bg-slate-200/70 hover:text-slate-700 focus:opacity-100 group-hover:opacity-100"
        >
            <span class="material-symbols-outlined text-[17px]">reply</span>
        </button>
    @endif

    <div
        data-message-bubble
        class="max-w-[82%] rounded-2xl px-4 py-2.5 shadow-sm transition-all duration-300 sm:max-w-[70%] lg:max-w-xl {{ $isOwn ? 'rounded-br-sm bg-blue-600 text-white' : 'rounded-bl-sm border border-slate-200/80 bg-white text-slate-800' }}"
    >
        @if($isGroup && ! $isOwn && $senderName !== '')
            <div class="mb-1 text-xs font-semibold text-purple-700" data-sender-name>
                {{ $senderName }}
            </div>
        @endif

        @if(is_array($replyTo) && ! empty($replyTo['id']))
            <button
                type="button"
                data-quote-jump="{{ $replyTo['id'] }}"
                class="mb-2 block w-full cursor-pointer rounded-lg border-l-4 px-3 py-1.5 text-left transition {{ $isOwn ? 'border-white/80 bg-blue-700/75 hover:bg-blue-700/90' : 'border-blue-500 bg-slate-100/90 hover:bg-slate-200/75' }}"
            >
                <div class="truncate text-[11px] font-semibold {{ $isOwn ? 'text-white' : 'text-blue-700' }}">
                    {{ $replyTo['sender_name'] ?? 'Pesan' }}
                </div>
                <p class="mt-0.5 line-clamp-2 text-xs {{ $isOwn ? 'text-blue-100' : 'text-slate-600' }}">
                    {{ $replyTo['body'] ?? '' }}
                </p>
            </button>
        @endif

        <p class="text-sm leading-relaxed whitespace-pre-line break-words" data-body>{{ $message->body }}</p>

        <div class="mt-1 flex items-center justify-end gap-1 {{ $isOwn ? 'text-blue-100' : 'text-slate-400' }}">
            <span class="text-[11px]" data-time>{{ $waktu }}</span>
            @if($isOwn && ! $isGroup)
                <span
                    data-read-icon
                    class="material-symbols-outlined text-[15px] leading-none {{ $message->is_read ? 'text-emerald-300' : 'text-blue-200' }}"
                    title="{{ $message->is_read ? 'Sudah dibaca' : 'Terkirim' }}"
                >{{ $message->is_read ? 'done_all' : 'done' }}</span>
            @endif
        </div>
    </div>

    @if(! $isOwn)
        <button
            type="button"
            data-reply-btn
            title="Balas pesan"
            aria-label="Balas pesan"
            class="flex h-7 w-7 flex-shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 opacity-0 transition-all hover:bg-slate-200/70 hover:text-slate-700 focus:opacity-100 group-hover:opacity-100"
        >
            <span class="material-symbols-outlined text-[17px]">reply</span>
        </button>
    @endif
</div>
