@props([
    'receiver',
])

<div class="flex-shrink-0 border-t border-slate-200 bg-white px-4 py-3 md:px-6">
    <!-- Form Input Pesan -->
    <form
        id="chat-send-form"
        method="POST"
        action="{{ route('chat.send', $receiver->id) }}"
        class="space-y-2"
    >
        @csrf
        <input type="hidden" id="reply-to-id" name="reply_to_id" value="" />

        <!-- Bar Preview Balasan Pesan (Reply Preview) -->
        <div
            id="reply-preview-bar"
            class="hidden items-center justify-between gap-3 rounded-xl border border-blue-100 border-l-4 border-l-blue-600 bg-blue-50/60 px-3.5 py-2"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-blue-700">
                    <span class="material-symbols-outlined text-[15px]">reply</span>
                    <span>Membalas <span id="reply-preview-sender"></span></span>
                </div>
                <p id="reply-preview-body" class="mt-0.5 truncate text-xs text-slate-600"></p>
            </div>

            <button
                type="button"
                id="cancel-reply-btn"
                title="Batalkan balasan (Esc)"
                aria-label="Batalkan balasan"
                class="flex h-6 w-6 flex-shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-blue-100 hover:text-slate-700"
            >
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>

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
