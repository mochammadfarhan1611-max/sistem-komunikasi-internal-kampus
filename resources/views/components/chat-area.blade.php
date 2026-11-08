@props([
    'receiver' => null,
    'messages' => collect(),
])

@if($receiver)
    @php
        $roleReceiver = $receiver->roleName();
        $avatarClasses = match ($roleReceiver) {
            'Dosen' => 'bg-blue-100 text-blue-700',
            'Mahasiswa' => 'bg-emerald-100 text-emerald-700',
            'Staff_Unit' => 'bg-amber-100 text-amber-700',
            default => 'bg-indigo-100 text-indigo-700',
        };
    @endphp

    <main class="flex h-full min-w-0 flex-1 flex-col bg-slate-50">
        <!-- Header Percakapan -->
        <header class="flex h-16 flex-shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    data-sidebar-open
                    aria-label="Buka daftar kontak"
                    class="-ml-1 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100 md:hidden"
                >
                    <span class="material-symbols-outlined text-[22px]">menu</span>
                </button>

                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $avatarClasses }}">
                    @if($roleReceiver === 'Staff_Unit')
                        <span class="material-symbols-outlined text-[20px]">support_agent</span>
                    @else
                        {{ $receiver->initials() }}
                    @endif
                </div>

                <div class="min-w-0">
                    <h1 class="truncate text-sm font-semibold text-slate-900 md:text-base">
                        {{ $receiver->nama_lengkap }}
                    </h1>
                    <p class="truncate text-xs text-slate-500">
                        <span>{{ $receiver->roleLabel() }}</span>
                        <span class="mx-1 text-slate-300">•</span>
                        <span class="font-mono">{{ $receiver->identityNumber() }}</span>
                        <span class="hidden sm:inline">
                            <span class="mx-1 text-slate-300">•</span>
                            <span>{{ $receiver->affiliationLabel() }}</span>
                        </span>
                    </p>
                </div>
            </div>

            <div class="flex flex-shrink-0 items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $receiver->status_akun === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $receiver->status_akun === 'Aktif' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    <span class="hidden sm:inline">{{ $receiver->status_akun }}</span>
                </span>
            </div>
        </header>

        <!-- Kanvas Pesan -->
        <div
            id="chat-messages-container"
            data-fetch-url="{{ route('chat.messages', $receiver->id) }}"
            data-current-user-id="{{ auth()->id() }}"
            class="flex-1 space-y-2.5 overflow-y-auto px-4 py-5 md:px-8"
        >
            @forelse($messages as $msg)
                @php
                    $tanggalPesan = $msg->created_at->timezone('Asia/Jakarta');
                    $tampilkanPemisah = $loop->first || ! $tanggalPesan->isSameDay($messages[$loop->index - 1]->created_at->timezone('Asia/Jakarta'));
                @endphp

                @if($tampilkanPemisah)
                    <div class="my-3 flex items-center justify-center" data-date-key="{{ $tanggalPesan->format('Y-m-d') }}">
                        <span class="rounded-full border border-slate-200/80 bg-white/95 px-3 py-1 text-[11px] font-medium text-slate-500 shadow-sm">
                            {{ $tanggalPesan->isToday() ? 'Hari Ini' : ($tanggalPesan->isYesterday() ? 'Kemarin' : $tanggalPesan->locale('id')->translatedFormat('l')) }}, {{ $tanggalPesan->locale('id')->translatedFormat('d F Y') }}
                        </span>
                    </div>
                @endif

                <x-message-bubble
                    :message="$msg"
                    :is-own="(int) $msg->sender_id === (int) auth()->id()"
                />
            @empty
                <div id="empty-chat-state" class="flex h-full flex-col items-center justify-center gap-2 text-center text-slate-400">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-blue-600 shadow-sm">
                        <span class="material-symbols-outlined text-[24px]">chat_bubble_outline</span>
                    </div>
                    <p class="text-sm font-medium text-slate-700">Belum ada percakapan</p>
                    <p class="max-w-xs text-xs text-slate-400">
                        Kirim pesan pertama untuk memulai komunikasi akademik dengan {{ $receiver->nama_lengkap }}.
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Bar Input Pesan -->
        <x-chat-input :receiver="$receiver" />
    </main>
@else
    <!-- Tampilan Kosong Saat Belum Memilih Kontak (Desktop) -->
    <main class="hidden h-full min-w-0 flex-1 flex-col items-center justify-center bg-slate-50 px-6 text-center md:flex">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-blue-600 shadow-sm">
            <span class="material-symbols-outlined text-[32px]">forum</span>
        </div>
        <h1 class="mt-4 text-lg font-semibold text-slate-900">
            Sistem Komunikasi Internal Kampus
        </h1>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
            Pilih salah satu kontak dosen, mahasiswa, atau unit layanan di panel kiri untuk mulai bertukar pesan secara real-time.
        </p>
        <div class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-500">
            <span class="material-symbols-outlined text-[14px] text-emerald-600">lock</span>
            <span>Jaringan Internal Sivitas Akademika</span>
        </div>
    </main>
@endif
