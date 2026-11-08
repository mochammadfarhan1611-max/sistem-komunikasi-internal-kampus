@props([
    'receiver',
])

@php
    $quickReplies = [
        'Sesi siang jam 13.30' => 'Ya, silakan hadir di ruang dosen jam 13.30 WIB.',
        'Lanjutkan Bab 4' => 'Bagus, lanjutkan penyusunan Bab 4 hasil dan pengujian.',
        'Siapkan slide 10 menit' => 'Tolong siapkan slide presentasi 10 menit untuk progres Jumat.',
    ];
@endphp

<div class="flex-shrink-0 border-t border-slate-200 bg-white px-4 py-3 md:px-6">
    <!-- Balasan Cepat Akademik -->
    <div class="mb-2.5 flex items-center gap-1.5 overflow-x-auto no-scrollbar">
        <span class="flex flex-shrink-0 items-center gap-1 text-[11px] font-medium text-slate-400">
            <span class="material-symbols-outlined text-[14px]">bolt</span>
            <span>Cepat:</span>
        </span>
        @foreach($quickReplies as $label => $teksLengkap)
            <button
                type="button"
                data-quick-text="{{ $teksLengkap }}"
                class="flex-shrink-0 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600 transition-colors hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Form Input Pesan -->
    <form
        id="chat-send-form"
        method="POST"
        action="{{ route('chat.send', $receiver->id) }}"
        class="space-y-2"
    >
        @csrf

        <div id="chat-ajax-error" class="hidden items-center gap-1.5 px-1 text-xs text-red-600">
            <span class="material-symbols-outlined text-[15px]">error</span>
            <span data-error-text></span>
        </div>

        @error('body')
            <div class="flex items-center gap-1.5 px-1 text-xs text-red-600">
                <span class="material-symbols-outlined text-[15px]">error</span>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <div class="flex items-center gap-2">
            <input
                id="academic-chat-input"
                type="text"
                name="body"
                value="{{ old('body') }}"
                maxlength="5000"
                required
                autofocus
                autocomplete="off"
                placeholder="Tulis pesan untuk {{ $receiver->nama_lengkap }}..."
                class="flex-1 rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-500/15"
            />

            <button
                id="send-message-btn"
                type="submit"
                aria-label="Kirim pesan"
                class="inline-flex h-10 flex-shrink-0 items-center justify-center gap-1.5 rounded-full bg-blue-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span class="hidden sm:inline">Kirim</span>
                <span class="material-symbols-outlined text-[18px]">send</span>
            </button>
        </div>
    </form>
</div>
