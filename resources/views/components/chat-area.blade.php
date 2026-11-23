@props([
    'receiver' => null,
    'messages' => collect(),
    'group' => null,
    'groupMembers' => collect(),
    'hasMoreMessages' => false,
])

@if($receiver)
    @php
        $isGroup = (bool) ($receiver->is_group ?? false);
        $groupType = $isGroup ? strtolower((string) ($receiver->group_type ?? $group?->type ?? 'kelas')) : '';
        $isPerwalian = $isGroup && $groupType === 'perwalian';
        $validCategories = ['all', 'Dosen', 'Mahasiswa', 'Staff_Unit', 'Group'];
        $reqCategory = (string) request()->query('category', 'all');
        $activeCategory = in_array($reqCategory, $validCategories, true) ? $reqCategory : 'all';
        $closeChatUrl = url('/dashboard').($activeCategory !== 'all' ? '?category='.urlencode($activeCategory) : '');
        $membersList = $isGroup
            ? ($groupMembers->isNotEmpty() ? $groupMembers : ($receiver->members ?? $group?->members ?? collect()))
            : collect();
        $roleReceiver = $isGroup ? 'Group' : $receiver->roleName();

        if ($isGroup) {
            $wsChannelKey = (string) ($receiver->group_id ?? \Illuminate\Support\Str::after((string) $receiver->id, 'group_'));
        } else {
            $pairIds = [(string) auth()->id(), (string) $receiver->id];
            sort($pairIds);
            $wsChannelKey = 'dm_'.implode('_', $pairIds);
        }
        $avatarClasses = match (true) {
            $isPerwalian => 'bg-teal-100 text-teal-700',
            $roleReceiver === 'Dosen' => 'bg-blue-100 text-blue-700',
            $roleReceiver === 'Mahasiswa' => 'bg-emerald-100 text-emerald-700',
            $roleReceiver === 'Staff_Unit' => 'bg-amber-100 text-amber-700',
            $roleReceiver === 'Group' => 'bg-purple-100 text-purple-700',
            default => 'bg-indigo-100 text-indigo-700',
        };
    @endphp

    <main id="active-chat-wrapper" class="flex-1 flex flex-col h-full min-w-0 bg-slate-50">
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
                    @if($isPerwalian)
                        <span class="material-symbols-outlined text-[20px]">supervisor_account</span>
                    @elseif($isGroup)
                        <span class="material-symbols-outlined text-[20px]">groups</span>
                    @elseif($roleReceiver === 'Staff_Unit')
                        <span class="material-symbols-outlined text-[20px]">support_agent</span>
                    @else
                        {{ $receiver->initials() }}
                    @endif
                </div>

                <div class="min-w-0">
                    <h1 class="truncate text-sm font-semibold text-slate-900 md:text-base">
                        {{ $receiver->nama_lengkap }}
                    </h1>
                    <p class="flex flex-wrap items-center gap-1 truncate text-xs text-slate-500">
                        @if($isGroup)
                            <span>{{ $isPerwalian ? 'Grup Perwalian & Bimbingan KRS' : 'Grup Kelas Perkuliahan' }}</span>
                            <span class="mx-0.5 text-slate-300">•</span>
                            <button
                                type="button"
                                id="open-group-members-modal"
                                data-modal-open="group-members-modal"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-0.5 font-medium transition hover:underline focus:outline-none focus:ring-2 {{ $isPerwalian ? 'bg-teal-50 text-teal-700 hover:bg-teal-100 focus:ring-teal-500/30' : 'bg-purple-50 text-purple-700 hover:bg-purple-100 focus:ring-purple-500/30' }}"
                                title="Klik untuk melihat daftar anggota grup"
                            >
                                <span class="material-symbols-outlined text-[14px]">group</span>
                                <span>{{ $receiver->members_count ?? $membersList->count() }} Anggota</span>
                            </button>
                            @if(!empty($receiver->kode_tahun_akademik))
                                <span class="hidden sm:inline">
                                    <span class="mx-0.5 text-slate-300">•</span>
                                    <span class="font-mono">{{ \App\Models\Group::formatKodeSemester((string) $receiver->kode_tahun_akademik) }}</span>
                                </span>
                            @endif
                        @else
                            <span>{{ $receiver->roleLabel() }}</span>
                            <span class="mx-1 text-slate-300">•</span>
                            <span class="font-mono">{{ $receiver->identityNumber() }}</span>
                            <span class="hidden sm:inline">
                                <span class="mx-1 text-slate-300">•</span>
                                <span>{{ $receiver->affiliationLabel() }}</span>
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-shrink-0 items-center gap-2">
                <span
                    id="ws-connection-badge"
                    data-ws-status="connecting"
                    title="Status koneksi real-time WebSocket (Laravel Reverb)"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 transition-colors"
                >
                    <span data-ws-dot class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span data-ws-label class="hidden sm:inline">Menghubungkan...</span>
                </span>

                <a
                    id="close-chat-btn"
                    href="{{ $closeChatUrl }}"
                    title="Tutup obrolan (Esc)"
                    aria-label="Tutup obrolan"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </a>
            </div>
        </header>

        <!-- Kanvas Pesan -->
        <div
            id="chat-messages-container"
            data-fetch-url="{{ route('chat.messages', $receiver->id) }}"
            data-current-user-id="{{ auth()->id() }}"
            data-is-group="{{ $isGroup ? '1' : '0' }}"
            data-group-id="{{ $wsChannelKey }}"
            class="flex-1 space-y-2.5 overflow-y-auto px-4 py-5 md:px-8"
        >
            <!-- Tombol Muat Pesan Sebelumnya (Pagination 50 Pesan) -->
            <div
                id="load-more-messages-wrapper"
                class="{{ $hasMoreMessages ? 'flex' : 'hidden' }} justify-center pb-2"
            >
                <button
                    type="button"
                    id="load-more-messages-btn"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-wait disabled:opacity-50"
                >
                    <span class="material-symbols-outlined text-[15px]">history</span>
                    <span data-load-more-text>Muat pesan sebelumnya</span>
                </button>
            </div>
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
                    :is-own="(string) ($msg->sender_identifier ?? $msg->sender_id) === (string) auth()->id()"
                    :is-group="$isGroup"
                />
            @empty
                <div id="empty-chat-state" class="flex h-full flex-col items-center justify-center gap-2 text-center text-slate-400">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white {{ $isGroup ? 'text-purple-600' : 'text-blue-600' }} shadow-sm">
                        <span class="material-symbols-outlined text-[24px]">{{ $isGroup ? 'groups' : 'chat_bubble_outline' }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-700">Belum ada percakapan</p>
                    <p class="max-w-xs text-xs text-slate-400">
                        Kirim pesan pertama untuk memulai diskusi di {{ $receiver->nama_lengkap }}.
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Bar Input Pesan -->
        <x-chat-input :receiver="$receiver" />

        @if($isGroup)
            <!-- Modal Daftar Anggota Grup -->
            <div
                id="group-members-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="group-members-modal-title"
                class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-[1px]"
            >
                <div class="flex max-h-[85vh] w-[92vw] max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <!-- Header Modal -->
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                                <span class="material-symbols-outlined text-[22px]">groups</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 id="group-members-modal-title" class="truncate text-sm font-semibold text-slate-900">
                                    Daftar Anggota Grup
                                </h3>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $receiver->nama_lengkap }} ({{ $membersList->count() }} Anggota)
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            data-modal-close="group-members-modal"
                            aria-label="Tutup modal anggota"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>

                    <!-- Bar Pencarian Anggota (Tetap di atas daftar scrollable) -->
                    <div class="shrink-0 border-b border-slate-100 bg-slate-50/70 px-5 py-3">
                        <div class="relative flex items-center">
                            <span class="material-symbols-outlined pointer-events-none absolute left-3 text-[18px] text-slate-400">search</span>
                            <input
                                type="text"
                                id="group-member-search-input"
                                autocomplete="off"
                                placeholder="Cari nama atau NIM/NIDN anggota..."
                                class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-9 text-xs text-slate-800 placeholder-slate-400 shadow-xs transition focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <button
                                type="button"
                                id="group-member-search-clear"
                                aria-label="Hapus pencarian anggota"
                                style="display: none;"
                                class="absolute right-2 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            >
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                        <p
                            id="group-member-search-count"
                            style="display: none;"
                            class="mt-1.5 text-[11px] font-medium text-slate-500"
                        ></p>
                    </div>

                    <!-- Body Modal: Daftar Anggota -->
                    <div id="group-members-list" class="max-h-[58vh] flex-1 divide-y divide-slate-100 overflow-y-auto px-5 py-2">
                        @forelse($membersList as $member)
                            @php
                                $isAdmin = strtolower((string) $member->role) === 'admin';
                                $memberIdentifier = (string) $member->user_identifier;
                                $rawMemberName = trim((string) ($member->user_name ?? ''));
                                $displayName = ($rawMemberName !== '' && ! \App\Services\UnujaPortalService::isPlaceholderName($rawMemberName, $memberIdentifier))
                                    ? $rawMemberName
                                    : ($isAdmin ? 'Dosen Pembimbing, M.Kom.' : $memberIdentifier);
                                $isCurrentUser = $memberIdentifier === (string) auth()->id();
                                $roleLabel = $isAdmin
                                    ? ($isPerwalian ? 'Dosen Wali / Admin' : 'Dosen Pembimbing / Admin')
                                    : 'Mahasiswa';
                            @endphp
                            <div
                                data-member-item
                                data-search-text="{{ strtolower($displayName . ' ' . $memberIdentifier . ' ' . $roleLabel) }}"
                                class="flex items-center justify-between gap-4 py-3"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $isAdmin ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        <span class="material-symbols-outlined text-[18px]">{{ $isAdmin ? 'school' : 'person' }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ $displayName }}
                                            </p>
                                            @if($isCurrentUser)
                                                <span class="shrink-0 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Anda</span>
                                            @endif
                                        </div>
                                        <p class="truncate font-mono text-xs text-slate-500">
                                            {{ $isAdmin ? 'NIDN' : 'NIM' }}: {{ $memberIdentifier }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isAdmin ? ($isPerwalian ? 'bg-teal-50 text-teal-700 ring-1 ring-teal-200' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-200') : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
                                        {{ $roleLabel }}
                                    </span>

                                    @if(! $isCurrentUser)
                                        <a
                                            href="{{ route('chat.show', $memberIdentifier) }}"
                                            title="Chat pribadi dengan {{ $displayName }}"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 active:scale-95"
                                        >
                                            <span class="material-symbols-outlined text-[15px]">chat</span>
                                            <span>Chat</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-slate-400">
                                Belum ada data anggota terdaftar di grup ini.
                            </div>
                        @endforelse

                        <div
                            id="group-member-search-empty"
                            style="display: none;"
                            class="flex-col items-center justify-center gap-1.5 py-10 text-center text-slate-400"
                        >
                            <span class="material-symbols-outlined text-[26px] text-slate-300">person_search</span>
                            <p class="text-xs font-medium text-slate-500">Anggota tidak ditemukan</p>
                        </div>
                    </div>

                    <!-- Footer Modal -->
                    <div class="flex shrink-0 items-center justify-end border-t border-slate-100 bg-slate-50 px-5 py-3">
                        <button
                            type="button"
                            data-modal-close="group-members-modal"
                            class="rounded-xl bg-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-300"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </main>
@endif

<!-- Tampilan Kosong Saat Belum Memilih Kontak / Setelah Menutup Obrolan (Selalu tersedia di DOM untuk Instant Close 0ms) -->
<main
    id="chat-empty-state"
    @if($receiver) style="display: none;" @endif
    class="{{ $receiver ? 'flex' : 'hidden md:flex' }} flex-1 flex-col h-full min-w-0 items-center justify-center bg-slate-50 px-6 text-center"
>
    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-blue-600 shadow-sm">
        <span class="material-symbols-outlined text-[32px]">forum</span>
    </div>
    <h1 class="mt-4 text-lg font-semibold text-slate-900">
        Sistem Komunikasi Internal Kampus
    </h1>
    <p class="mt-1 max-w-sm text-sm text-slate-500">
        Pilih salah satu kontak dosen, mahasiswa, grup kelas perkuliahan, atau unit layanan di panel kiri untuk mulai bertukar pesan secara real-time.
    </p>
    <div class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-500">
        <span class="material-symbols-outlined text-[14px] text-emerald-600">lock</span>
        <span>Jaringan Internal Sivitas Akademika</span>
    </div>
</main>
