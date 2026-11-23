@props([
    'contact',
    'active' => false,
    'initialCategory' => 'all',
])

@php
    $isGroup = $contact->is_group ?? false;
    $groupType = $isGroup ? strtolower((string) ($contact->group_type ?? $contact->type ?? 'kelas')) : '';
    $isPerwalian = $isGroup && $groupType === 'perwalian';

    // Jika ini adalah Grup, gunakan atribut grup yang aman tanpa error 'undefined method'
    if ($isGroup) {
        $role = 'Group';
        $roleLabel = $isPerwalian ? 'Perwalian PA' : 'Grup Kelas';
        $initials = $isPerwalian ? 'PA' : 'GR';
        $identityNumber = $isPerwalian
            ? 'Perwalian Akademik'
            : (! empty($contact->kode_tahun_akademik)
                ? \App\Models\Group::formatKodeSemester((string) $contact->kode_tahun_akademik)
                : 'Grup Sistem');
        $subtitle = isset($contact->members_count) ? $contact->members_count.' Anggota' : 'Komunikasi Internal';
        $statusAkun = 'Aktif';
    } else {
        // Jika ini adalah Pengguna Personal, panggil metode bawaan Model User
        $role = $contact->roleName();
        $roleLabel = $contact->roleLabel();
        $initials = $contact->initials();
        $identityNumber = $contact->identityNumber();
        $subtitle = $contact->contactSubtitle();
        $statusAkun = $contact->status_akun;
    }

    $unreadCount = (int) ($contact->unread_count ?? 0);
    $lastMessage = $contact->last_message;
    $hasLastMessage = is_array($lastMessage) && ! empty($lastMessage['timestamp']);
    $isOwnLastMessage = $hasLastMessage && ! empty($lastMessage['is_sender_me']);
    $showUnreadBadge = $unreadCount > 0 && ! $active;

    $avatarClasses = match (true) {
        $isPerwalian => 'bg-teal-100 text-teal-700',
        $role === 'Dosen' => 'bg-blue-100 text-blue-700',
        $role === 'Mahasiswa' => 'bg-emerald-100 text-emerald-700',
        $role === 'Staff_Unit' => 'bg-amber-100 text-amber-700',
        $role === 'Group' => 'bg-purple-100 text-purple-700',
        default => 'bg-indigo-100 text-indigo-700',
    };

    $roleChipClasses = match (true) {
        $isPerwalian => $active ? 'bg-teal-100 text-teal-800 ring-1 ring-teal-300' : 'bg-teal-50 text-teal-700 ring-1 ring-teal-200/80',
        $active => 'bg-blue-100/80 text-blue-700',
        default => 'bg-slate-100 text-slate-500',
    };

    $prodiName = (string) ($contact->prodi?->nama_prodi ?? '');
    $fakultasName = (string) ($contact->fakultas?->nama_fakultas ?? '');
    $unitName = (string) ($contact->unitKampus?->nama_unit_kerja ?? '');

    $searchTerms = strtolower(trim(implode(' ', array_filter([
        (string) $contact->nama_lengkap,
        (string) $contact->id,
        (string) ($contact->identifier ?? ''),
        (string) $identityNumber,
        $prodiName,
        $fakultasName,
        $unitName,
        $role,
        $roleLabel,
        $subtitle,
    ]))));
    $isHiddenByCategory = $initialCategory !== 'all' && strcasecmp((string) $role, (string) $initialCategory) !== 0;
    $itemUrl = route('chat.show', $contact->id).($initialCategory !== 'all' ? '?category='.urlencode((string) $initialCategory) : '');
@endphp

<a
    href="{{ $itemUrl }}"
    data-contact-item
    data-contact-id="{{ $contact->id }}"
    data-contact-name="{{ $contact->nama_lengkap }}"
    data-contact-identity="{{ $identityNumber }}"
    data-group-type="{{ $groupType }}"
    data-last-message-id="{{ $lastMessage['id'] ?? 0 }}"
    data-last-message-at="{{ $lastMessage['timestamp'] ?? '' }}"
    data-contact-role="{{ $role }}"
    data-contact-search="{{ $searchTerms }}"
    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $isHiddenByCategory ? 'hidden' : '' }} {{ $active ? 'bg-blue-50/90 text-blue-950' : 'text-slate-800 hover:bg-slate-50' }}"
>
    <div class="relative flex-shrink-0">
        <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold {{ $avatarClasses }}">
            @if($role === 'Staff_Unit')
                <span class="material-symbols-outlined text-[20px]">support_agent</span>
            @elseif($isPerwalian)
                <span class="material-symbols-outlined text-[20px]">supervisor_account</span>
            @elseif($role === 'Group')
                <span class="material-symbols-outlined text-[20px]">groups</span>
            @else
                {{ $initials }}
            @endif
        </div>
    </div>

    <div class="min-w-0 flex-1">
        <!-- Baris 1: Nama, Badge Kategori (selalu tampil), & Waktu Pesan Terakhir -->
        <div class="flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-1.5">
                <span class="truncate text-sm {{ $active ? 'font-semibold text-blue-700' : 'font-medium text-slate-900' }}">
                    {{ $contact->nama_lengkap }}
                </span>

                <span
                    data-contact-role-chip
                    class="flex-shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $roleChipClasses }}"
                >{{ $roleLabel }}</span>
            </div>

            <span
                data-contact-time
                class="flex-shrink-0 text-[11px] {{ $hasLastMessage ? '' : 'hidden' }} {{ $showUnreadBadge ? 'font-semibold text-emerald-600' : 'text-slate-400' }}"
            >{{ $hasLastMessage ? ($lastMessage['time'] ?? '') : '' }}</span>
        </div>

        <!-- Baris 2: Cuplikan Pesan Terakhir / Info Prodi & Badge Unread -->
        <div class="mt-0.5 flex items-center justify-between gap-2">
            <p
                data-contact-preview
                class="flex min-w-0 items-center gap-1 text-xs {{ $showUnreadBadge ? 'font-medium text-slate-700' : 'text-slate-500' }}"
            >
                @if($isOwnLastMessage)
                    <span
                        data-contact-read-icon
                        class="material-symbols-outlined flex-shrink-0 text-[15px] leading-none {{ ! empty($lastMessage['is_read']) ? 'text-blue-500' : 'text-slate-400' }}"
                        title="{{ ! empty($lastMessage['is_read']) ? 'Sudah dibaca' : 'Terkirim' }}"
                    >{{ ! empty($lastMessage['is_read']) ? 'done_all' : 'done' }}</span>
                @endif

                <span data-contact-preview-text class="truncate">
                    @if($hasLastMessage)
                        {{ $lastMessage['body'] }}
                    @else
                        <span class="font-mono text-[11px] text-slate-400">{{ $identityNumber }}</span>
                        <span class="mx-1 text-slate-300">•</span>
                        <span>{{ $subtitle }}</span>
                    @endif
                </span>
            </p>

            <span
                data-unread-badge
                class="{{ $showUnreadBadge ? 'inline-flex' : 'hidden' }} h-5 min-w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-semibold text-white"
            >{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        </div>
    </div>
</a>