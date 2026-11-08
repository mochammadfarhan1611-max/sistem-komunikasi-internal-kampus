@props([
    'kontak',
    'receiver' => null,
    'tahunAktif' => null,
])

@php
    $currentUser = auth()->user();
    $hasActiveChat = $receiver !== null;
@endphp

<aside
    id="chat-sidebar"
    aria-label="Daftar Kontak Sivitas Akademika"
    class="flex h-full flex-col border-r border-slate-200 bg-white {{ $hasActiveChat ? 'fixed inset-y-0 left-0 z-40 w-[86vw] max-w-sm -translate-x-full shadow-xl transition-transform duration-200 ease-out md:static md:z-auto md:w-80 md:max-w-none md:translate-x-0 md:shadow-none lg:w-96' : 'w-full md:w-80 md:flex-shrink-0 lg:w-96' }}"
>
    <!-- Profil Pengguna & Aksi Sesi -->
    <div class="border-b border-slate-100 px-4 py-3.5">
        <div class="flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-3">
                <div class="relative flex-shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                        {{ $currentUser->initials() }}
                    </div>
                    <span class="absolute right-0 bottom-0 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                </div>
                <div class="min-w-0">
                    <h2 class="truncate text-sm font-semibold text-slate-900">
                        {{ $currentUser->nama_lengkap }}
                    </h2>
                    <p class="truncate text-xs text-slate-500">
                        <span class="font-mono">{{ $currentUser->identityBadge() }}</span>
                        <span class="mx-1 text-slate-300">•</span>
                        <span>{{ $currentUser->affiliationLabel() }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-shrink-0 items-center gap-1">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        title="Keluar dari Portal"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                    >
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                    </button>
                </form>

                @if($hasActiveChat)
                    <button
                        type="button"
                        data-sidebar-close
                        aria-label="Tutup daftar kontak"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 md:hidden"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="mt-2.5 flex items-center justify-between rounded-lg bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-600">
            <span class="flex items-center gap-1.5 font-medium truncate">
                <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500"></span>
                <span class="truncate">{{ $tahunAktif?->nama_semester ?? 'Semester Aktif' }}</span>
            </span>
            <span class="flex-shrink-0 font-mono text-slate-400">{{ $kontak->count() }} kontak</span>
        </div>
    </div>

    <!-- Pencarian & Filter Peran -->
    <div class="space-y-2.5 border-b border-slate-100 px-3 py-3">
        <div class="relative">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-slate-400">search</span>
            <input
                id="contact-search-input"
                type="search"
                autocomplete="off"
                placeholder="Cari nama, NIM/NIDN, atau unit..."
                class="w-full rounded-xl bg-slate-100 py-2 pr-3 pl-9 text-sm text-slate-800 placeholder:text-slate-400 outline-none transition focus:bg-white focus:ring-2 focus:ring-blue-500/20"
            />
        </div>

        <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 no-scrollbar">
            <button
                type="button"
                data-role-filter="all"
                class="rounded-full bg-blue-600 px-3 py-1 text-xs font-medium text-white transition-colors"
            >
                Semua
            </button>
            <button
                type="button"
                data-role-filter="Dosen"
                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-200"
            >
                Dosen
            </button>
            <button
                type="button"
                data-role-filter="Mahasiswa"
                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-200"
            >
                Mahasiswa
            </button>
            <button
                type="button"
                data-role-filter="Staff_Unit"
                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-200"
            >
                Layanan
            </button>
        </div>
    </div>

    <!-- Daftar Kontak (diurutkan dari percakapan terbaru, disinkronkan via polling) -->
    <div
        id="contact-list-container"
        data-summary-url="{{ route('chat.contacts') }}"
        data-active-contact-id="{{ $receiver?->id ?? '' }}"
        class="flex-1 space-y-0.5 overflow-y-auto px-2 py-2"
    >
        @forelse($kontak as $k)
            <x-contact-item
                :contact="$k"
                :active="$hasActiveChat && $receiver->id === $k->id"
            />
        @empty
            <div class="px-4 py-10 text-center text-xs text-slate-400">
                Belum ada kontak sivitas akademika lainnya.
            </div>
        @endforelse

        <div id="contact-search-empty" class="hidden px-4 py-10 text-center text-xs text-slate-400">
            Kontak yang dicari tidak ditemukan.
        </div>
    </div>
</aside>

