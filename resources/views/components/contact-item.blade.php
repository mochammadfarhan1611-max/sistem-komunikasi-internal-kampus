@props([
    'contact',
    'active' => false,
])

@php
    $role = $contact->roleName();
    $unreadCount = (int) ($contact->unread_count ?? 0);
    $lastMessage = $contact->last_message;
    $hasLastMessage = is_array($lastMessage);
    $isOwnLastMessage = $hasLastMessage && $lastMessage['is_sender_me'];
    $showUnreadBadge = $unreadCount > 0 && ! $active;

    $avatarClasses = match ($role) {
        'Dosen' => 'bg-blue-100 text-blue-700',
        'Mahasiswa' => 'bg-emerald-100 text-emerald-700',
        'Staff_Unit' => 'bg-amber-100 text-amber-700',
        default => 'bg-indigo-100 text-indigo-700',
    };

    $searchTerms = strtolower(
        $contact->nama_lengkap.' '.$contact->identityNumber().' '.$contact->roleLabel().' '.$contact->contactSubtitle()
    );
@endphp

<a
    href="{{ route('chat.show', $contact->id) }}"
    data-contact-item
    data-contact-id="{{ $contact->id }}"
    data-last-message-id="{{ $lastMessage['id'] ?? 0 }}"
    data-last-message-at="{{ $lastMessage['timestamp'] ?? '' }}"
    data-contact-role="{{ $role }}"
    data-contact-search="{{ $searchTerms }}"
    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $active ? 'bg-blue-50/90 text-blue-950' : 'text-slate-800 hover:bg-slate-50' }}"
>
    <div class="relative flex-shrink-0">
        <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold {{ $avatarClasses }}">
            @if($role === 'Staff_Unit')
                <span class="material-symbols-outlined text-[20px]">support_agent</span>
            @else
                {{ $contact->initials() }}
            @endif
        </div>
        <span
            class="absolute right-0 bottom-0 h-3 w-3 rounded-full ring-2 ring-white {{ $contact->status_akun === 'Aktif' ? 'bg-emerald-500' : 'bg-slate-300' }}"
            title="Status: {{ $contact->status_akun }}"
        ></span>
    </div>

    <div class="min-w-0 flex-1">
        <!-- Baris 1: Nama & Waktu Pesan Terakhir -->
        <div class="flex items-center justify-between gap-2">
            <span class="truncate text-sm {{ $active ? 'font-semibold text-blue-700' : 'font-medium text-slate-900' }}">
                {{ $contact->nama_lengkap }}
            </span>

            <span
                data-contact-time
                class="flex-shrink-0 text-[11px] {{ $hasLastMessage ? '' : 'hidden' }} {{ $showUnreadBadge ? 'font-semibold text-emerald-600' : 'text-slate-400' }}"
            >{{ $lastMessage['time'] ?? '' }}</span>

            <span
                data-contact-role-chip
                class="flex-shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $hasLastMessage ? 'hidden' : '' }} {{ $active ? 'bg-blue-100/80 text-blue-700' : 'bg-slate-100 text-slate-500' }}"
            >{{ $contact->roleLabel() }}</span>
        </div>

        <!-- Baris 2: Cuplikan Pesan Terakhir / Info Prodi & Badge Unread -->
        <div class="mt-0.5 flex items-center justify-between gap-2">
            <p
                data-contact-preview
                class="flex min-w-0 items-center gap-1 text-xs {{ $showUnreadBadge ? 'font-medium text-slate-700' : 'text-slate-500' }}"
            >
                <span
                    data-contact-read-icon
                    class="material-symbols-outlined flex-shrink-0 text-[15px] leading-none {{ $isOwnLastMessage ? '' : 'hidden' }} {{ $isOwnLastMessage && $lastMessage['is_read'] ? 'text-blue-500' : 'text-slate-400' }}"
                    title="{{ $isOwnLastMessage && $lastMessage['is_read'] ? 'Sudah dibaca' : 'Terkirim' }}"
                >{{ $isOwnLastMessage && $lastMessage['is_read'] ? 'done_all' : 'done' }}</span>

                <span data-contact-preview-text class="truncate">
                    @if($hasLastMessage)
                        {{ $lastMessage['body'] }}
                    @else
                        <span class="font-mono text-[11px] text-slate-400">{{ $contact->identityNumber() }}</span>
                        <span class="mx-1 text-slate-300">•</span>
                        <span>{{ $contact->contactSubtitle() }}</span>
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
