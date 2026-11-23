@props([
    'kontak',
    'receiver' => null,
    'tahunAktif' => null,
    'categoryCounts' => [],
    'hasMoreContacts' => false,
    'loadedCategoryCount' => 0,
])

@php
    $currentUser = auth()->user();
    $hasActiveChat = $receiver !== null;
    $validCategories = ['all', 'Dosen', 'Mahasiswa', 'Staff_Unit', 'Group'];
    $requestedCategory = (string) request()->query('category', 'all');
    $initialCategory = in_array($requestedCategory, $validCategories, true) ? $requestedCategory : 'all';
    $initialCategoryTotal = (int) ($categoryCounts[$initialCategory] ?? $kontak->count());
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
                    <span
                        id="sidebar-ws-dot"
                        title="Status WebSocket: Menghubungkan..."
                        class="absolute right-0 bottom-0 h-2.5 w-2.5 rounded-full bg-amber-500 ring-2 ring-white animate-pulse"
                    ></span>
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
            <span id="sidebar-contact-count" class="flex-shrink-0 font-mono text-slate-400">{{ $initialCategoryTotal }} kontak</span>
        </div>
    </div>

    <!-- Pencarian & Filter Peran -->
    <div class="space-y-2.5 border-b border-slate-100 px-3 py-3">
        <div class="relative">
            <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[18px] text-slate-400">search</span>
            <input
                id="contact-search-input"
                type="text"
                autocomplete="off"
                data-search-url="{{ route('api.search') }}"
                placeholder="Cari nama, NIM/NIDN, atau mata kuliah..."
                class="w-full rounded-xl bg-slate-100 py-2 pr-9 pl-9 text-sm text-slate-800 placeholder:text-slate-400 outline-none transition focus:bg-white focus:ring-2 focus:ring-blue-500/20"
            />
            <button
                type="button"
                id="contact-search-clear"
                aria-label="Hapus pencarian"
                title="Bersihkan pencarian"
                class="hidden absolute top-1/2 right-2.5 -translate-y-1/2 h-5 w-5 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600"
            >
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>

        <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 no-scrollbar">
            <button
                type="button"
                data-role-filter="all"
                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $initialCategory === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Semua
            </button>
            <button
                type="button"
                data-role-filter="Dosen"
                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $initialCategory === 'Dosen' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Dosen
            </button>
            <button
                type="button"
                data-role-filter="Mahasiswa"
                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $initialCategory === 'Mahasiswa' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Mahasiswa
            </button>
            <button
                type="button"
                data-role-filter="Staff_Unit"
                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $initialCategory === 'Staff_Unit' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Layanan
            </button>
            <button
                type="button"
                data-role-filter="Group"
                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $initialCategory === 'Group' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                Grup
            </button>
        </div>
    </div>

    <!-- Daftar Kontak (diurutkan dari percakapan terbaru, disinkronkan via WebSocket) -->
    <div
        id="contact-list-container"
        data-summary-url="{{ route('chat.contacts') }}"
        data-active-contact-id="{{ $receiver?->id ?? '' }}"
        data-server-category="{{ $initialCategory }}"
        data-category-counts='@json($categoryCounts)'
        data-has-more-contacts="{{ $hasMoreContacts ? '1' : '0' }}"
        data-loaded-category-count="{{ $loadedCategoryCount }}"
        class="flex-1 space-y-0.5 overflow-y-auto px-2 py-2"
    >
        @forelse($kontak as $k)
            <x-contact-item
                :contact="$k"
                :active="$hasActiveChat && (string) $receiver->id === (string) $k->id"
                :initialCategory="$initialCategory"
            />
        @empty
            <div class="px-4 py-10 text-center text-xs text-slate-400">
                Belum ada kontak sivitas akademika lainnya.
            </div>
        @endforelse

        <!-- Tombol Muat Lebih Banyak (khusus tab Dosen & Mahasiswa dengan pagination 40 kontak) -->
        <div
            id="load-more-contacts-wrapper"
            class="{{ $hasMoreContacts ? 'flex' : 'hidden' }} justify-center py-2.5"
        >
            <button
                type="button"
                id="load-more-contacts-btn"
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-wait disabled:opacity-50"
            >
                <span class="material-symbols-outlined text-[15px]">expand_more</span>
                <span data-load-more-contacts-text>Muat lebih banyak...</span>
            </button>
        </div>

        <div id="contact-search-empty" class="hidden px-4 py-10 text-center text-xs text-slate-400">
            Kontak yang dicari tidak ditemukan.
        </div>
    </div>

    <!-- Inisialisasi sinkron sebelum first paint agar tab filter kategori tidak berkedip kembali ke "Semua" saat pindah ruang chat -->
    <script>
        (function () {
            try {
                var storageKey = 'skik_active_category_filter';
                var validCategories = ['all', 'Dosen', 'Mahasiswa', 'Staff_Unit', 'Group'];
                var params = new URLSearchParams(window.location.search);
                var urlCategory = params.get('category');
                var storedCategory = sessionStorage.getItem(storageKey);
                var activeCategory = validCategories.indexOf(urlCategory) !== -1
                    ? urlCategory
                    : (validCategories.indexOf(storedCategory) !== -1 ? storedCategory : 'all');

                sessionStorage.setItem(storageKey, activeCategory);

                var sidebar = document.getElementById('chat-sidebar');
                if (!sidebar) {
                    return;
                }

                var listEl = document.getElementById('contact-list-container');
                var countEl = document.getElementById('sidebar-contact-count');
                if (listEl && countEl) {
                    try {
                        var counts = JSON.parse(listEl.getAttribute('data-category-counts') || '{}');
                        if (typeof counts[activeCategory] === 'number') {
                            countEl.textContent = counts[activeCategory] + ' kontak';
                        }
                    } catch (err) {}
                }

                var buttons = sidebar.querySelectorAll('[data-role-filter]');
                buttons.forEach(function (btn) {
                    var isMatch = btn.getAttribute('data-role-filter') === activeCategory;
                    btn.classList.toggle('bg-blue-600', isMatch);
                    btn.classList.toggle('text-white', isMatch);
                    btn.classList.toggle('bg-slate-100', !isMatch);
                    btn.classList.toggle('text-slate-600', !isMatch);
                    btn.classList.toggle('hover:bg-slate-200', !isMatch);
                });

                var items = sidebar.querySelectorAll('[data-contact-item]');
                var filterLower = activeCategory.toLowerCase();
                var visibleCount = 0;

                items.forEach(function (item) {
                    var role = (item.getAttribute('data-contact-role') || '').toLowerCase();
                    var matches = filterLower === 'all' || role === filterLower;
                    item.classList.toggle('hidden', !matches);
                    if (matches) {
                        visibleCount++;
                    }
                    var rawHref = item.getAttribute('href');
                    if (rawHref) {
                        var u = new URL(rawHref, window.location.origin);
                        if (activeCategory !== 'all') {
                            u.searchParams.set('category', activeCategory);
                        } else {
                            u.searchParams.delete('category');
                        }
                        item.setAttribute('href', u.pathname + u.search);
                    }
                });

                var emptyEl = document.getElementById('contact-search-empty');
                var isLazyRole = activeCategory === 'Dosen' || activeCategory === 'Mahasiswa';
                if (emptyEl && items.length > 0 && !isLazyRole) {
                    emptyEl.classList.toggle('hidden', visibleCount > 0);
                }
            } catch (e) {}
        })();
    </script>
</aside>