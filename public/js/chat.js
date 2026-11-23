document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Drawer Sidebar Toggle
    const sidebar = document.getElementById('chat-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');

    const openSidebar = () => {
        if (!sidebar) {
            return;
        }
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        backdrop?.classList.remove('hidden');
    };

    const closeSidebar = () => {
        if (!sidebar) {
            return;
        }
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        backdrop?.classList.add('hidden');
    };

    document.querySelectorAll('[data-sidebar-open]').forEach((btn) => {
        btn.addEventListener('click', openSidebar);
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((btn) => {
        btn.addEventListener('click', closeSidebar);
    });

    backdrop?.addEventListener('click', closeSidebar);

    // 1b. Modal Daftar Anggota Grup & Live Search Sisi Klien (0 ms)
    const groupMembersModal = document.getElementById('group-members-modal');
    const groupMembersList = document.getElementById('group-members-list');
    const groupMemberSearchInput = document.getElementById('group-member-search-input');
    const groupMemberSearchClearBtn = document.getElementById('group-member-search-clear');
    const groupMemberSearchCount = document.getElementById('group-member-search-count');
    const groupMemberSearchEmpty = document.getElementById('group-member-search-empty');
    const groupMemberItems = groupMembersModal
        ? Array.from(groupMembersModal.querySelectorAll('[data-member-item]'))
        : [];

    const filterGroupMembers = () => {
        const rawValue = groupMemberSearchInput ? (groupMemberSearchInput.value || '') : '';
        const query = rawValue.trim().toLowerCase();
        const totalCount = groupMemberItems.length;
        let visibleCount = 0;

        if (groupMemberSearchClearBtn) {
            groupMemberSearchClearBtn.style.display = rawValue.length > 0 ? 'flex' : 'none';
        }

        groupMemberItems.forEach((item) => {
            const haystack = (item.dataset.searchText || '').toLowerCase();
            const isMatch = query === '' || haystack.includes(query);
            item.style.display = isMatch ? '' : 'none';
            if (isMatch) {
                visibleCount += 1;
            }
        });

        if (groupMemberSearchEmpty) {
            const showEmpty = query !== '' && totalCount > 0 && visibleCount === 0;
            groupMemberSearchEmpty.style.display = showEmpty ? 'flex' : 'none';
        }

        if (groupMemberSearchCount) {
            if (query !== '' && totalCount > 0) {
                groupMemberSearchCount.textContent = `Menampilkan ${visibleCount} dari ${totalCount} anggota`;
                groupMemberSearchCount.style.display = 'block';
            } else {
                groupMemberSearchCount.textContent = '';
                groupMemberSearchCount.style.display = 'none';
            }
        }
    };

    const resetGroupMemberSearch = () => {
        if (groupMemberSearchInput) {
            groupMemberSearchInput.value = '';
        }
        filterGroupMembers();
        if (groupMembersList) {
            groupMembersList.scrollTop = 0;
        }
    };

    const openGroupMembersModal = () => {
        if (!groupMembersModal) {
            return;
        }
        resetGroupMemberSearch();
        groupMembersModal.classList.remove('hidden');
        groupMembersModal.classList.add('flex');
    };

    const closeGroupMembersModal = () => {
        if (!groupMembersModal) {
            return;
        }
        resetGroupMemberSearch();
        groupMembersModal.classList.add('hidden');
        groupMembersModal.classList.remove('flex');
    };

    groupMemberSearchInput?.addEventListener('input', filterGroupMembers);

    groupMemberSearchClearBtn?.addEventListener('click', () => {
        resetGroupMemberSearch();
        groupMemberSearchInput?.focus();
    });

    document.querySelectorAll('[data-modal-open="group-members-modal"]').forEach((btn) => {
        btn.addEventListener('click', openGroupMembersModal);
    });

    document.querySelectorAll('[data-modal-close="group-members-modal"]').forEach((btn) => {
        btn.addEventListener('click', closeGroupMembersModal);
    });

    groupMembersModal?.addEventListener('click', (e) => {
        if (e.target === groupMembersModal) {
            closeGroupMembersModal();
        }
    });

    // 2. Pencarian AJAX (Debounce) & Filter Kategori Kontak di Sidebar (Persisten via sessionStorage & URL Parameter)
    const CATEGORY_STORAGE_KEY = 'skik_active_category_filter';
    const VALID_CATEGORIES = ['all', 'Dosen', 'Mahasiswa', 'Staff_Unit', 'Group'];
    const CATEGORY_NORMALIZATION_MAP = {
        all: 'all',
        semua: 'all',
        dosen: 'Dosen',
        mahasiswa: 'Mahasiswa',
        staff_unit: 'Staff_Unit',
        layanan: 'Staff_Unit',
        group: 'Group',
        grup: 'Group',
    };

    const normalizeCategoryFilter = (rawCategory) => {
        const key = String(rawCategory || '').trim().toLowerCase();
        return CATEGORY_NORMALIZATION_MAP[key] || 'all';
    };

    const searchInput = document.getElementById('contact-search-input');
    const searchClearBtn = document.getElementById('contact-search-clear');
    const filterButtons = document.querySelectorAll('[data-role-filter]');
    const searchEmptyState = document.getElementById('contact-search-empty');
    const contactList = document.getElementById('contact-list-container');
    const summaryUrl = contactList?.dataset.summaryUrl;
    let activeContactId = String(contactList?.dataset.activeContactId || '');
    let activeReceiverId = activeContactId || null;
    const searchApiUrl = searchInput?.dataset.searchUrl || '/api/search';

    const resolveInitialCategoryFilter = () => {
        try {
            const params = new URLSearchParams(window.location.search);
            const urlCat = params.get('category');
            if (urlCat) {
                const normalizedUrlCat = normalizeCategoryFilter(urlCat);
                if (VALID_CATEGORIES.includes(normalizedUrlCat)) {
                    return normalizedUrlCat;
                }
            }
            const storedCat = sessionStorage.getItem(CATEGORY_STORAGE_KEY);
            if (storedCat) {
                const normalizedStoredCat = normalizeCategoryFilter(storedCat);
                if (VALID_CATEGORIES.includes(normalizedStoredCat)) {
                    return normalizedStoredCat;
                }
            }
        } catch (e) {
            // Abaikan jika sessionStorage diblokir
        }
        return 'all';
    };

    let activeRoleFilter = resolveInitialCategoryFilter();
    let searchDebounceTimer = null;
    let searchAbortController = null;
    let loadMoreAbortController = null;
    let activeListRequestSeq = 0;

    const buildCategoryAwareUrl = (rawHref, category = activeRoleFilter) => {
        if (!rawHref) {
            return rawHref;
        }
        try {
            const normalizedCategory = normalizeCategoryFilter(category);
            const url = new URL(rawHref, window.location.origin);
            if (normalizedCategory !== 'all' && VALID_CATEGORIES.includes(normalizedCategory)) {
                url.searchParams.set('category', normalizedCategory);
            } else {
                url.searchParams.delete('category');
            }
            return url.pathname + url.search;
        } catch (e) {
            return rawHref;
        }
    };

    const syncFilterButtonsUI = (category = activeRoleFilter) => {
        const normalizedCategory = normalizeCategoryFilter(category);
        filterButtons.forEach((btn) => {
            const btnCategory = normalizeCategoryFilter(btn.dataset.roleFilter || 'all');
            const isActive = btnCategory === normalizedCategory;
            btn.classList.toggle('bg-blue-600', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('bg-slate-100', !isActive);
            btn.classList.toggle('text-slate-600', !isActive);
            btn.classList.toggle('hover:bg-slate-200', !isActive);
        });
    };

    const persistCategoryFilterState = (category) => {
        activeRoleFilter = normalizeCategoryFilter(category);

        try {
            sessionStorage.setItem(CATEGORY_STORAGE_KEY, activeRoleFilter);
        } catch (e) {
            // Abaikan jika sessionStorage tidak tersedia
        }

        try {
            const currentUrl = new URL(window.location.href);
            if (activeRoleFilter !== 'all') {
                currentUrl.searchParams.set('category', activeRoleFilter);
            } else {
                currentUrl.searchParams.delete('category');
            }
            window.history.replaceState({}, '', currentUrl.pathname + currentUrl.search);
        } catch (e) {
            // Abaikan jika history API gagal
        }

        syncFilterButtonsUI(activeRoleFilter);

        // Perbarui href seluruh link kontak/grup & tombol tutup chat agar membawa parameter kategori saat diklik
        contactList?.querySelectorAll('[data-contact-item]').forEach((item) => {
            item.href = buildCategoryAwareUrl(item.getAttribute('href'), activeRoleFilter);
        });
        defaultContactNodes.forEach((node) => {
            node.href = buildCategoryAwareUrl(node.getAttribute('href'), activeRoleFilter);
        });

        const closeChatBtn = document.getElementById('close-chat-btn');
        if (closeChatBtn) {
            closeChatBtn.href = buildCategoryAwareUrl('/dashboard', activeRoleFilter);
        }
    };

    // Simpan snapshot elemen daftar normal bawaan agar saat reset pencarian langsung mulus tanpa jeda dan bebas duplikat
    let defaultContactNodes = [];
    const snapshotDefaultContactNodes = () => {
        if (!contactList) {
            return;
        }
        const seen = new Set();
        const nodes = [];
        contactList.querySelectorAll('[data-contact-item]').forEach((el) => {
            const cid = String(el.dataset.contactId || '').trim();
            if (!cid || seen.has(cid)) {
                return;
            }
            seen.add(cid);
            const clone = el.cloneNode(true);
            clone.classList.remove('hidden');
            clone.href = buildCategoryAwareUrl(clone.getAttribute('href'), activeRoleFilter);
            nodes.push(clone);
        });
        defaultContactNodes = nodes;
    };
    snapshotDefaultContactNodes();

    const updateClearButtonVisibility = () => {
        if (!searchClearBtn || !searchInput) {
            return;
        }
        const hasText = (searchInput.value || '').trim().length > 0;
        searchClearBtn.classList.toggle('hidden', !hasText);
        searchClearBtn.classList.toggle('flex', hasText);
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    };

    const getAvatarClasses = (role, groupType = 'kelas') => {
        if (role === 'Group' && groupType === 'perwalian') {
            return 'bg-teal-100 text-teal-700';
        }
        switch (role) {
            case 'Dosen':
                return 'bg-blue-100 text-blue-700';
            case 'Mahasiswa':
                return 'bg-emerald-100 text-emerald-700';
            case 'Staff_Unit':
                return 'bg-amber-100 text-amber-700';
            case 'Group':
                return 'bg-purple-100 text-purple-700';
            default:
                return 'bg-indigo-100 text-indigo-700';
        }
    };

    const formatAcademicPeriod = (rawCode) => {
        const clean = String(rawCode || '').trim();
        if (!clean) {
            return '';
        }
        const match = clean.match(/(?:^|semester\s*)(\d{4})([123])$/i);
        if (!match) {
            return clean;
        }
        const year = Number(match[1]);
        const termMap = { '1': 'Ganjil', '2': 'Genap', '3': 'Antara' };
        const termLabel = termMap[match[2]] || 'Semester';
        return `${termLabel} ${year}/${year + 1}`;
    };

    const buildContactItemElement = (item) => {
        const isGroup = Boolean(item.is_group || item.role === 'Group');
        const groupType = isGroup
            ? String(item.group_type || (String(item.nama_lengkap || '').toLowerCase().startsWith('perwalian') ? 'perwalian' : 'kelas')).toLowerCase()
            : '';
        const isPerwalian = isGroup && groupType === 'perwalian';
        const role = item.role || (isGroup ? 'Group' : 'Mahasiswa');
        const roleLabel = item.role_label || (isGroup ? (isPerwalian ? 'Perwalian PA' : 'Grup Kelas') : role);
        const initials = item.initials || (isGroup ? (isPerwalian ? 'PA' : 'GR') : 'UN');
        const rawIdentity = item.identity_number || item.kode_tahun_akademik || item.id || '';
        const identityNumber = isGroup
            ? (isPerwalian ? 'Perwalian Akademik' : formatAcademicPeriod(rawIdentity))
            : rawIdentity;
        const subtitle = item.subtitle || 'Komunikasi Internal';
        const statusAkun = item.status_akun || 'Aktif';
        const displayName = item.nama_lengkap || item.name || item.id || '';
        const isActive = String(item.id) === activeContactId;
        const unreadCount = isActive ? 0 : Number(item.unread_count || 0);
        const showUnreadBadge = unreadCount > 0 && !isActive;
        const lastMessage = item.last_message || null;
        const hasLastMessage = lastMessage && typeof lastMessage === 'object' && Boolean(lastMessage.timestamp);
        const isOwnLastMessage = hasLastMessage && Boolean(lastMessage.is_sender_me);
        const avatarClasses = getAvatarClasses(role, groupType);
        const roleChipClasses = isPerwalian
            ? (isActive ? 'bg-teal-100 text-teal-800 ring-1 ring-teal-300' : 'bg-teal-50 text-teal-700 ring-1 ring-teal-200/80')
            : (isActive ? 'bg-blue-100/80 text-blue-700' : 'bg-slate-100 text-slate-500');

        const searchTerms = [
            displayName,
            item.id,
            identityNumber,
            role,
            roleLabel,
            subtitle,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        const anchor = document.createElement('a');
        const baseHref = item.url || `/dashboard/${encodeURIComponent(item.id)}`;
        anchor.href = buildCategoryAwareUrl(baseHref, activeRoleFilter);
        anchor.setAttribute('data-contact-item', '');
        anchor.dataset.contactId = String(item.id);
        anchor.dataset.contactName = String(displayName);
        anchor.dataset.contactIdentity = String(identityNumber);
        anchor.dataset.groupType = groupType;
        anchor.dataset.lastMessageId = String(hasLastMessage ? lastMessage.id || 0 : 0);
        anchor.dataset.lastMessageAt = hasLastMessage ? lastMessage.timestamp || '' : '';
        anchor.dataset.contactRole = role;
        anchor.dataset.contactSearch = searchTerms;
        anchor.className = `group flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors ${
            isActive ? 'bg-blue-50/90 text-blue-950' : 'text-slate-800 hover:bg-slate-50'
        }`;

        let avatarInner = escapeHtml(initials);
        if (role === 'Staff_Unit') {
            avatarInner = '<span class="material-symbols-outlined text-[20px]">support_agent</span>';
        } else if (isPerwalian) {
            avatarInner = '<span class="material-symbols-outlined text-[20px]">supervisor_account</span>';
        } else if (role === 'Group') {
            avatarInner = '<span class="material-symbols-outlined text-[20px]">groups</span>';
        }

        const previewHtml = hasLastMessage
            ? escapeHtml(lastMessage.body || '')
            : `<span class="font-mono text-[11px] text-slate-400">${escapeHtml(identityNumber)}</span><span class="mx-1 text-slate-300">•</span><span>${escapeHtml(subtitle)}</span>`;

        const readIconHtml = isOwnLastMessage
            ? `<span data-contact-read-icon class="material-symbols-outlined flex-shrink-0 text-[15px] leading-none ${lastMessage.is_read ? 'text-blue-500' : 'text-slate-400'}" title="${lastMessage.is_read ? 'Sudah dibaca' : 'Terkirim'}">${lastMessage.is_read ? 'done_all' : 'done'}</span>`
            : '';

        anchor.innerHTML = `
            <div class="relative flex-shrink-0">
                <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold ${avatarClasses}">
                    ${avatarInner}
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-1.5">
                        <span class="truncate text-sm ${isActive ? 'font-semibold text-blue-700' : 'font-medium text-slate-900'}">
                            ${escapeHtml(displayName)}
                        </span>
                        <span
                            data-contact-role-chip
                            class="flex-shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium ${roleChipClasses}"
                        >${escapeHtml(roleLabel)}</span>
                    </div>
                    <span
                        data-contact-time
                        class="flex-shrink-0 text-[11px] ${hasLastMessage ? '' : 'hidden'} ${showUnreadBadge ? 'font-semibold text-emerald-600' : 'text-slate-400'}"
                    >${hasLastMessage ? escapeHtml(lastMessage.time || '') : ''}</span>
                </div>
                <div class="mt-0.5 flex items-center justify-between gap-2">
                    <p
                        data-contact-preview
                        class="flex min-w-0 items-center gap-1 text-xs ${showUnreadBadge ? 'font-medium text-slate-700' : 'text-slate-500'}"
                    >
                        ${readIconHtml}
                        <span data-contact-preview-text class="truncate">${previewHtml}</span>
                    </p>
                    <span
                        data-unread-badge
                        class="${showUnreadBadge ? 'inline-flex' : 'hidden'} h-5 min-w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-semibold text-white"
                    >${unreadCount > 99 ? '99+' : unreadCount}</span>
                </div>
            </div>
        `;

        return anchor;
    };

    /**
     * Hitung skor relevansi elemen DOM terhadap kata kunci pencarian (Top Relevance Match).
     * Grup KRS hanya boleh cocok jika nama mata kuliah / kode kelasnya mengandung kata kunci.
     */
    const computeNodeRelevanceScore = (node, keyword) => {
        const kw = (keyword || '').trim().toLowerCase();
        if (!kw) {
            return 0;
        }

        const role = (node.dataset.contactRole || '').toLowerCase();
        const isGroup = role === 'group';
        const name = (node.dataset.contactName || node.querySelector('.truncate.text-sm')?.textContent || '').trim().toLowerCase();
        const id = (node.dataset.contactId || '').trim().toLowerCase();
        const identity = (node.dataset.contactIdentity || '').trim().toLowerCase();

        if (isGroup) {
            const cleanCourseName = name.replace(/^mk\s+/i, '').trim();
            if (!name.includes(kw) && !cleanCourseName.includes(kw) && !identity.includes(kw)) {
                return 0;
            }
            if (cleanCourseName === kw || name === kw) {
                return 950;
            }
            if (cleanCourseName.startsWith(kw) || name.startsWith(kw)) {
                return 780;
            }
            const courseWords = cleanCourseName.split(/[\s,.\-\/()]+/).filter(Boolean);
            for (let i = 0; i < courseWords.length; i++) {
                if (courseWords[i] === kw) {
                    return 680 + Math.max(0, 10 - i);
                }
            }
            for (let i = 0; i < courseWords.length; i++) {
                if (courseWords[i].startsWith(kw)) {
                    return 580 + Math.max(0, 10 - i);
                }
            }
            return 280;
        }

        const matchesName = name.includes(kw);
        const matchesId = id.includes(kw) || identity.includes(kw);
        const matchesService = role === 'staff_unit' && (node.dataset.contactSearch || '').includes(kw);

        if (!matchesName && !matchesId && !matchesService) {
            return 0;
        }

        if (id === kw || identity === kw || name === kw) {
            return 1000;
        }
        if (name.startsWith(kw) || id.startsWith(kw) || identity.startsWith(kw)) {
            return 850;
        }

        const words = name.split(/[\s,.\-\/()]+/).filter(Boolean);
        for (let i = 0; i < words.length; i++) {
            if (words[i] === kw) {
                return 750 + Math.max(0, 10 - i);
            }
        }
        if (kw.includes(' ') && matchesName) {
            return 720;
        }
        for (let i = 0; i < words.length; i++) {
            if (words[i].startsWith(kw)) {
                return 650 + Math.max(0, 10 - i);
            }
        }
        if (matchesId) {
            return 500;
        }
        if (matchesName) {
            return 350;
        }
        return 300;
    };

    const applyContactFilter = () => {
        if (!contactList) {
            return;
        }
        const contactItems = contactList.querySelectorAll('[data-contact-item]');
        let visibleCount = 0;
        const filterTarget = (activeRoleFilter || 'all').toLowerCase();

        contactItems.forEach((item) => {
            const role = (item.dataset.contactRole || '').toLowerCase();
            const matchesRole = filterTarget === 'all' || role === filterTarget;

            item.classList.toggle('hidden', !matchesRole);
            if (matchesRole) {
                visibleCount++;
            }
        });

        if (searchEmptyState) {
            searchEmptyState.classList.toggle('hidden', visibleCount > 0);
        }
    };

    const restoreDefaultContactList = () => {
        if (!contactList) {
            return;
        }

        contactList.querySelectorAll('[data-contact-item]').forEach((el) => el.remove());

        const seenIds = new Set();
        const fragment = document.createDocumentFragment();

        // Urutkan defaultContactNodes: percakapan dengan pesan nyata di atas (timestamp terbaru descending), sisanya urutan proporsional
        const sortedDefaultNodes = [...defaultContactNodes].sort((a, b) => {
            const tsA = String(a.dataset.lastMessageAt || '');
            const tsB = String(b.dataset.lastMessageAt || '');
            const hasMsgA = tsA !== '';
            const hasMsgB = tsB !== '';

            if (hasMsgA !== hasMsgB) {
                return hasMsgA ? -1 : 1;
            }
            if (hasMsgA && hasMsgB) {
                if (tsA !== tsB) {
                    return tsB.localeCompare(tsA);
                }
                const idA = Number(a.dataset.lastMessageId || 0);
                const idB = Number(b.dataset.lastMessageId || 0);
                if (idA !== idB) {
                    return idB - idA;
                }
            }

            const cleanA = (a.dataset.contactName || '').replace(/^mk\s+/i, '').trim();
            const cleanB = (b.dataset.contactName || '').replace(/^mk\s+/i, '').trim();
            return cleanA.localeCompare(cleanB, 'id', { sensitivity: 'base' });
        });

        sortedDefaultNodes.forEach((node) => {
            const cid = String(node.dataset.contactId || '').trim();
            if (!cid || seenIds.has(cid)) {
                return;
            }
            seenIds.add(cid);
            fragment.appendChild(node.cloneNode(true));
        });

        const anchorBefore = document.getElementById('load-more-contacts-wrapper') || searchEmptyState;
        if (anchorBefore) {
            contactList.insertBefore(fragment, anchorBefore);
        } else {
            contactList.appendChild(fragment);
        }

        applyContactFilter();
    };

    const applyInstantClientSearch = (keyword) => {
        if (!contactList) {
            return;
        }

        const kw = (keyword || '').trim();
        if (!kw) {
            restoreDefaultContactList();
            return;
        }

        const scored = [];
        const seenIds = new Set();

        defaultContactNodes.forEach((node) => {
            const cid = String(node.dataset.contactId || '').trim();
            if (!cid || seenIds.has(cid)) {
                return;
            }
            const score = computeNodeRelevanceScore(node, kw);
            if (score > 0) {
                seenIds.add(cid);
                scored.push({ node: node.cloneNode(true), score });
            }
        });

        scored.sort((a, b) => {
            if (b.score !== a.score) {
                return b.score - a.score;
            }
            const isGroupA = (a.node.dataset.contactRole || '').toLowerCase() === 'group';
            const isGroupB = (b.node.dataset.contactRole || '').toLowerCase() === 'group';
            if (isGroupA !== isGroupB) {
                return isGroupA ? 1 : -1;
            }
            const tsA = String(a.node.dataset.lastMessageAt || '');
            const tsB = String(b.node.dataset.lastMessageAt || '');
            if (tsA !== tsB) {
                return tsB.localeCompare(tsA);
            }
            const nameA = a.node.dataset.contactName || '';
            const nameB = b.node.dataset.contactName || '';
            return nameA.localeCompare(nameB, 'id', { sensitivity: 'base' });
        });

        contactList.querySelectorAll('[data-contact-item]').forEach((el) => el.remove());

        const fragment = document.createDocumentFragment();
        scored.forEach(({ node }) => fragment.appendChild(node));

        const anchorBefore = document.getElementById('load-more-contacts-wrapper') || searchEmptyState;
        if (anchorBefore) {
            contactList.insertBefore(fragment, anchorBefore);
        } else {
            contactList.appendChild(fragment);
        }

        applyContactFilter();
    };

    const sidebarContactCountEl = document.getElementById('sidebar-contact-count');
    const loadMoreContactsWrapper = document.getElementById('load-more-contacts-wrapper');
    const loadMoreContactsBtn = document.getElementById('load-more-contacts-btn');

    let categoryCountsMap = {};
    try {
        categoryCountsMap = JSON.parse(contactList?.dataset.categoryCounts || '{}');
    } catch (e) {
        categoryCountsMap = {};
    }

    const serverRenderedCategory = normalizeCategoryFilter(contactList?.dataset.serverCategory || 'all');
    let currentRoleOffset = Number(contactList?.dataset.loadedCategoryCount || 0);
    let currentRoleHasMore = contactList?.dataset.hasMoreContacts === '1';
    let isLoadingMoreContacts = false;

    const resetLoadMoreButtonUI = () => {
        isLoadingMoreContacts = false;
        const textEl = loadMoreContactsBtn?.querySelector('[data-load-more-contacts-text]');
        if (loadMoreContactsBtn) {
            loadMoreContactsBtn.disabled = false;
        }
        if (textEl) {
            textEl.textContent = 'Muat lebih banyak...';
        }
    };

    const abortActiveListRequests = () => {
        if (searchAbortController) {
            searchAbortController.abort();
            searchAbortController = null;
        }
        if (loadMoreAbortController) {
            loadMoreAbortController.abort();
            loadMoreAbortController = null;
        }
        resetLoadMoreButtonUI();
    };

    const updateSidebarCountBadge = (overrideCount = null, isSearchMode = false) => {
        if (!sidebarContactCountEl) {
            return;
        }
        if (overrideCount !== null && overrideCount !== undefined) {
            sidebarContactCountEl.textContent = isSearchMode
                ? `${overrideCount} hasil`
                : `${overrideCount} kontak`;
            return;
        }
        const knownCount = categoryCountsMap[activeRoleFilter];
        if (typeof knownCount === 'number') {
            sidebarContactCountEl.textContent = `${knownCount} kontak`;
        } else if (contactList) {
            const visibleItems = contactList.querySelectorAll('[data-contact-item]:not(.hidden)').length;
            sidebarContactCountEl.textContent = `${visibleItems} kontak`;
        }
    };

    const updateLoadMoreContactsVisibility = () => {
        if (!loadMoreContactsWrapper) {
            return;
        }
        const keyword = (searchInput?.value || '').trim();
        const shouldShow = keyword === '' && isLazyRoleTab(activeRoleFilter) && currentRoleHasMore;
        loadMoreContactsWrapper.classList.toggle('hidden', !shouldShow);
        loadMoreContactsWrapper.classList.toggle('flex', shouldShow);
    };

    const getListInsertionAnchor = () => loadMoreContactsWrapper || searchEmptyState || null;

    const renderSearchResults = (results, isDefaultList = false, appendMode = false) => {
        if (!contactList) {
            return;
        }

        const seenIds = new Set();
        if (appendMode) {
            contactList.querySelectorAll('[data-contact-item]').forEach((el) => {
                const cid = String(el.dataset.contactId || '').trim();
                if (cid) {
                    seenIds.add(cid);
                }
            });
        } else {
            contactList.querySelectorAll('[data-contact-item]').forEach((el) => el.remove());
        }

        const fragment = document.createDocumentFragment();

        results.forEach((item) => {
            const itemId = String(item?.id || '').trim();
            if (!itemId || seenIds.has(itemId)) {
                return;
            }
            seenIds.add(itemId);
            fragment.appendChild(buildContactItemElement(item));
        });

        const anchorNode = getListInsertionAnchor();
        if (anchorNode) {
            contactList.insertBefore(fragment, anchorNode);
        } else {
            contactList.appendChild(fragment);
        }

        if (isDefaultList && !appendMode) {
            snapshotDefaultContactNodes();
        }

        applyContactFilter();
    };

    const isLazyRoleTab = (role) => {
        const normalized = normalizeCategoryFilter(role);
        return normalized === 'Dosen' || normalized === 'Mahasiswa';
    };

    const performAjaxSearch = async (keyword, roleOverride = activeRoleFilter, offset = 0, appendMode = false) => {
        const targetRole = normalizeCategoryFilter(roleOverride || activeRoleFilter || 'all');
        const safeOffset = Math.max(0, Number(offset) || 0);
        let requestSeq = activeListRequestSeq;

        if (!appendMode) {
            abortActiveListRequests();
            requestSeq = ++activeListRequestSeq;
        } else if (loadMoreAbortController) {
            loadMoreAbortController.abort();
            loadMoreAbortController = null;
        }

        const controller = new AbortController();
        if (appendMode) {
            loadMoreAbortController = controller;
        } else {
            searchAbortController = controller;
        }

        const queryUrl = `${searchApiUrl}?keyword=${encodeURIComponent(keyword)}&role=${encodeURIComponent(targetRole)}&offset=${safeOffset}&limit=40`;

        try {
            const response = await fetch(queryUrl, {
                headers: {
                    'Accept': 'application/json',
                },
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const currentInputKeyword = (searchInput?.value || '').trim();

            // Tolak respons basi jika kategori aktif, keyword, atau urutan request sudah berubah (anti race-condition)
            if (
                requestSeq !== activeListRequestSeq
                || targetRole !== activeRoleFilter
                || currentInputKeyword !== keyword
            ) {
                return;
            }

            const items = Array.isArray(data) ? data : (data.results || []);
            const shouldSnapshotAsDefault = !appendMode && keyword === '' && !isLazyRoleTab(targetRole);
            renderSearchResults(items, shouldSnapshotAsDefault, appendMode);

            if (!appendMode && contactList) {
                contactList.scrollTop = 0;
            }

            if (keyword === '' && isLazyRoleTab(targetRole)) {
                currentRoleOffset = appendMode ? (safeOffset + items.length) : items.length;
                currentRoleHasMore = Boolean(data.has_more);
                if (typeof data.total === 'number') {
                    categoryCountsMap[targetRole] = data.total;
                }
                updateSidebarCountBadge(categoryCountsMap[targetRole], false);
            } else if (keyword !== '') {
                currentRoleOffset = 0;
                currentRoleHasMore = false;
                updateSidebarCountBadge(typeof data.total === 'number' ? data.total : items.length, true);
            } else {
                currentRoleOffset = 0;
                currentRoleHasMore = false;
                if (typeof data.total === 'number') {
                    categoryCountsMap[targetRole] = data.total;
                }
                updateSidebarCountBadge(categoryCountsMap[targetRole], false);
            }

            updateLoadMoreContactsVisibility();
        } catch (err) {
            if (err?.name !== 'AbortError') {
                console.error('Pencarian gagal:', err);
            }
        } finally {
            if (appendMode && loadMoreAbortController === controller) {
                loadMoreAbortController = null;
            } else if (!appendMode && searchAbortController === controller) {
                searchAbortController = null;
            }
        }
    };

    const loadMoreContactsForActiveTab = async () => {
        if (isLoadingMoreContacts || !currentRoleHasMore || !isLazyRoleTab(activeRoleFilter)) {
            return;
        }
        if ((searchInput?.value || '').trim() !== '') {
            return;
        }

        const expectedSeq = activeListRequestSeq;
        const expectedRole = activeRoleFilter;
        isLoadingMoreContacts = true;
        const textEl = loadMoreContactsBtn?.querySelector('[data-load-more-contacts-text]');
        if (loadMoreContactsBtn) {
            loadMoreContactsBtn.disabled = true;
        }
        if (textEl) {
            textEl.textContent = 'Memuat...';
        }

        try {
            await performAjaxSearch('', expectedRole, currentRoleOffset, true);
        } finally {
            if (expectedSeq === activeListRequestSeq) {
                resetLoadMoreButtonUI();
                updateLoadMoreContactsVisibility();
            }
        }
    };

    loadMoreContactsBtn?.addEventListener('click', loadMoreContactsForActiveTab);

    contactList?.addEventListener('scroll', () => {
        if (!currentRoleHasMore || isLoadingMoreContacts || !isLazyRoleTab(activeRoleFilter)) {
            return;
        }
        if ((searchInput?.value || '').trim() !== '') {
            return;
        }
        const nearBottom = contactList.scrollHeight - contactList.scrollTop - contactList.clientHeight < 80;
        if (nearBottom) {
            loadMoreContactsForActiveTab();
        }
    });

    const handleSearchInputChange = () => {
        updateClearButtonVisibility();

        if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
        }

        const keyword = (searchInput?.value || '').trim();

        if (keyword === '') {
            abortActiveListRequests();
            if (contactList) {
                contactList.scrollTop = 0;
            }
            if (isLazyRoleTab(activeRoleFilter)) {
                currentRoleOffset = 0;
                currentRoleHasMore = false;
                updateLoadMoreContactsVisibility();
                performAjaxSearch('', activeRoleFilter, 0, false);
            } else {
                restoreDefaultContactList();
                currentRoleOffset = 0;
                currentRoleHasMore = false;
                updateLoadMoreContactsVisibility();
                updateSidebarCountBadge(categoryCountsMap[activeRoleFilter], false);
                performAjaxSearch('', activeRoleFilter, 0, false);
            }
            return;
        }

        abortActiveListRequests();
        currentRoleOffset = 0;
        currentRoleHasMore = false;
        updateLoadMoreContactsVisibility();

        // Terapkan filter & pengurutan relevansi instan di sisi klien jika tidak sedang di tab lazy-load
        if (!isLazyRoleTab(activeRoleFilter)) {
            applyInstantClientSearch(keyword);
        }

        searchDebounceTimer = setTimeout(() => {
            performAjaxSearch(keyword, activeRoleFilter, 0, false);
        }, 250);
    };

    searchInput?.addEventListener('input', handleSearchInputChange);
    searchInput?.addEventListener('search', handleSearchInputChange);

    searchClearBtn?.addEventListener('click', () => {
        if (!searchInput) {
            return;
        }
        searchInput.value = '';
        handleSearchInputChange();
        searchInput.focus();
    });

    const loadedLazyRoles = new Set();
    if (isLazyRoleTab(serverRenderedCategory)) {
        loadedLazyRoles.add(serverRenderedCategory);
    }

    const handleCategoryTabActivation = (rawCategory) => {
        const selectedCategory = normalizeCategoryFilter(rawCategory);
        abortActiveListRequests();
        persistCategoryFilterState(selectedCategory);

        // Selalu reset posisi scroll dan state pagination ke halaman pertama setiap berpindah kategori
        if (contactList) {
            contactList.scrollTop = 0;
        }
        currentRoleOffset = 0;
        currentRoleHasMore = false;
        updateLoadMoreContactsVisibility();

        const currentKeyword = (searchInput?.value || '').trim();

        if (currentKeyword !== '') {
            performAjaxSearch(currentKeyword, selectedCategory, 0, false);
            return;
        }

        if (isLazyRoleTab(selectedCategory)) {
            // Selalu muat ulang Halaman 1 (offset=0) secara bersih agar tidak pernah terpengaruh snapshot non-lazy / offset lama
            loadedLazyRoles.add(selectedCategory);
            updateSidebarCountBadge(categoryCountsMap[selectedCategory], false);
            performAjaxSearch('', selectedCategory, 0, false);
        } else if (isLazyRoleTab(serverRenderedCategory) && !loadedLazyRoles.has('all_base')) {
            loadedLazyRoles.add('all_base');
            performAjaxSearch('', selectedCategory, 0, false);
        } else {
            ++activeListRequestSeq;
            restoreDefaultContactList();
            updateSidebarCountBadge(categoryCountsMap[selectedCategory], false);
            updateLoadMoreContactsVisibility();
        }
    };

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedCategory = normalizeCategoryFilter(button.dataset.roleFilter || 'all');
            handleCategoryTabActivation(selectedCategory);
        });
    });

    // Pastikan saat halaman selesai dimuat, status kategori dari sessionStorage/URL langsung diterapkan.
    // Jika server sudah merender 40 kontak untuk kategori aktif (Dosen/Mahasiswa), JANGAN hapus lalu fetch ulang!
    persistCategoryFilterState(activeRoleFilter);
    if (isLazyRoleTab(activeRoleFilter) && serverRenderedCategory !== activeRoleFilter) {
        loadedLazyRoles.add(activeRoleFilter);
        currentRoleOffset = 0;
        performAjaxSearch((searchInput?.value || '').trim(), activeRoleFilter, 0, false);
    } else {
        applyContactFilter();
        updateSidebarCountBadge(categoryCountsMap[activeRoleFilter], false);
        updateLoadMoreContactsVisibility();
    }

    if (activeContactId && contactList) {
        const activeItemEl = contactList.querySelector(`[data-contact-item][data-contact-id="${activeContactId}"]`);
        if (activeItemEl && !activeItemEl.classList.contains('hidden')) {
            activeItemEl.scrollIntoView({ block: 'nearest' });
        }
    }

    // Elemen-elemen Reply / Balas Pesan
    const replyToIdInput = document.getElementById('reply-to-id');
    const replyPreviewBar = document.getElementById('reply-preview-bar');
    const replyPreviewSender = document.getElementById('reply-preview-sender');
    const replyPreviewBody = document.getElementById('reply-preview-body');
    const cancelReplyBtn = document.getElementById('cancel-reply-btn');

    const clearReplyState = () => {
        if (replyToIdInput) {
            replyToIdInput.value = '';
        }
        if (replyPreviewSender) {
            replyPreviewSender.textContent = '';
        }
        if (replyPreviewBody) {
            replyPreviewBody.textContent = '';
        }
        if (replyPreviewBar) {
            replyPreviewBar.classList.add('hidden');
            replyPreviewBar.classList.remove('flex');
        }
    };

    const activateReplyState = (messageId, senderName, messageBody) => {
        if (!replyToIdInput || !replyPreviewBar) {
            return;
        }
        replyToIdInput.value = String(messageId || '');
        if (replyPreviewSender) {
            replyPreviewSender.textContent = String(senderName || 'Pesan');
        }
        if (replyPreviewBody) {
            replyPreviewBody.textContent = String(messageBody || '');
        }
        replyPreviewBar.classList.remove('hidden');
        replyPreviewBar.classList.add('flex');

        const chatInput = document.getElementById('academic-chat-input');
        chatInput?.focus();
    };

    cancelReplyBtn?.addEventListener('click', () => {
        clearReplyState();
        document.getElementById('academic-chat-input')?.focus();
    });

    // Elemen-elemen Ruang Chat Aktif & Empty State (untuk Client-Side Instant Close 0ms)
    const activeChatWrapper = document.getElementById('active-chat-wrapper');
    const chatEmptyState = document.getElementById('chat-empty-state');
    const closeChatBtnEl = document.getElementById('close-chat-btn');
    const container = document.getElementById('chat-messages-container');
    let activeWsGroupId = String(container?.dataset.groupId || '');

    const isElementVisible = (el) => {
        if (!el) {
            return false;
        }
        return window.getComputedStyle(el).display !== 'none' && !el.classList.contains('hidden');
    };

    const removeActiveHighlightFromContactNode = (item) => {
        if (!item) {
            return;
        }
        item.classList.remove('bg-blue-50/90', 'text-blue-950');
        item.classList.add('text-slate-800', 'hover:bg-slate-50');

        const nameEl = item.querySelector('.truncate.text-sm');
        if (nameEl) {
            nameEl.classList.remove('font-semibold', 'text-blue-700');
            nameEl.classList.add('font-medium', 'text-slate-900');
        }

        const roleChipEl = item.querySelector('[data-contact-role-chip]');
        if (roleChipEl) {
            const isPerwalianItem = (item.dataset.groupType || '').toLowerCase() === 'perwalian';
            if (isPerwalianItem) {
                roleChipEl.classList.remove('bg-teal-100', 'text-teal-800', 'ring-teal-300');
                roleChipEl.classList.add('bg-teal-50', 'text-teal-700', 'ring-teal-200/80');
            } else {
                roleChipEl.classList.remove('bg-blue-100/80', 'text-blue-700');
                roleChipEl.classList.add('bg-slate-100', 'text-slate-500');
            }
        }
    };

    const closeActiveChatInstant = () => {
        if (!activeChatWrapper || !isElementVisible(activeChatWrapper)) {
            return;
        }

        // 1. Sembunyikan ruang chat aktif & tampilkan panel empty state secara instan (0ms, tanpa reload)
        activeChatWrapper.style.display = 'none';
        if (chatEmptyState) {
            chatEmptyState.classList.remove('hidden');
            chatEmptyState.style.display = 'flex';
        }

        // 2. Lepas channel WebSocket khusus ruang chat yang barusan ditutup (JANGAN putus Echo atau sidebar.updates)
        if (window.Echo && activeWsGroupId) {
            try {
                window.Echo.leave(`group.${activeWsGroupId}`);
            } catch (e) {
                // Abaikan jika channel sudah terlepas
            }
        }

        // 3. Reset seluruh state JS & atribut data-* ruang chat aktif agar pesan baru tetap memunculkan badge unread
        activeContactId = '';
        activeReceiverId = null;
        activeWsGroupId = '';
        if (contactList) {
            contactList.dataset.activeContactId = '';
        }
        if (container) {
            container.dataset.groupId = '';
            container.dataset.fetchUrl = '';
        }
        clearReplyState();
        closeGroupMembersModal();

        // 4. Hapus sorotan aktif (highlight biru) dari item kontak yang terpilih di sidebar maupun snapshot default
        contactList?.querySelectorAll('[data-contact-item]').forEach(removeActiveHighlightFromContactNode);
        defaultContactNodes.forEach(removeActiveHighlightFromContactNode);

        // 5. Perbarui URL browser tanpa reload (pertahankan ?category= jika bukan all) & ubah judul dokumen
        try {
            sessionStorage.setItem(CATEGORY_STORAGE_KEY, activeRoleFilter || 'all');
        } catch (e) {
            // Abaikan jika sessionStorage tidak tersedia
        }

        const targetUrl = buildCategoryAwareUrl('/dashboard', activeRoleFilter);
        try {
            window.history.pushState({ closedChat: true, category: activeRoleFilter }, '', targetUrl);
        } catch (e) {
            // Abaikan jika History API tidak tersedia
        }
        document.title = 'Dashboard Komunikasi — SKIK';

        // Pada layar mobile, tampilkan sidebar daftar kontak setelah ruang chat ditutup
        if (window.innerWidth < 768) {
            openSidebar();
        }
    };

    closeChatBtnEl?.addEventListener('click', (event) => {
        event.preventDefault();
        closeActiveChatInstant();
    });

    window.addEventListener('popstate', () => {
        window.location.reload();
    });

    // 2b. Shortcut Keyboard Global (Esc): Prioritas Modal -> Reply Preview -> Pencarian Sidebar -> Tutup Obrolan Instan
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        // Prioritas 1: Jika Modal Daftar Anggota benar-benar terlihat
        // - Fokus di #group-member-search-input + berisi teks -> kosongkan pencarian saja
        // - Input kosong / fokus di luar input -> tutup modal saja (jangan tutup ruang chat di belakangnya)
        if (isElementVisible(groupMembersModal)) {
            event.preventDefault();
            if (
                groupMemberSearchInput
                && document.activeElement === groupMemberSearchInput
                && (groupMemberSearchInput.value || '').length > 0
            ) {
                resetGroupMemberSearch();
                return;
            }
            closeGroupMembersModal();
            return;
        }

        const openDialogs = document.querySelectorAll('[role="dialog"]');
        for (const dialog of openDialogs) {
            if (isElementVisible(dialog)) {
                event.preventDefault();
                dialog.classList.add('hidden');
                dialog.classList.remove('flex');
                return;
            }
        }

        if (isElementVisible(backdrop)) {
            event.preventDefault();
            closeSidebar();
            return;
        }

        // Prioritas 2: Jika bar Reply Preview benar-benar terlihat -> batalkan mode reply saja
        if (isElementVisible(replyPreviewBar)) {
            event.preventDefault();
            clearReplyState();
            return;
        }

        // Prioritas 3: Jika fokus sedang di kotak pencarian sidebar (#contact-search-input) DAN berisi teks -> bersihkan & blur saja
        if (searchInput && document.activeElement === searchInput && (searchInput.value || '').trim().length > 0) {
            event.preventDefault();
            searchInput.value = '';
            handleSearchInputChange();
            searchInput.blur();
            return;
        }

        // Prioritas 4: Jika sedang ada ruang obrolan aktif yang terlihat (termasuk saat fokus di #academic-chat-input) -> tutup instan 0ms
        if (isElementVisible(activeChatWrapper)) {
            event.preventDefault();
            closeActiveChatInstant();
        }
    });

    // 4. Sinkronisasi Sidebar: Unread Badge, Preview Pesan Terakhir & Auto-Reorder (Personal & Grup)

    const swapClasses = (el, isOn, onClasses, offClasses) => {
        if (!el) {
            return;
        }
        onClasses.forEach((cls) => el.classList.toggle(cls, isOn));
        offClasses.forEach((cls) => el.classList.toggle(cls, !isOn));
    };

    const updateContactItem = (item, summary) => {
        const lastMessage = summary.last_message;
        const isActiveContact = activeContactId !== '' && String(summary.contact_id) === activeContactId;
        const unreadCount = isActiveContact ? 0 : Number(summary.unread_count || 0);
        const hasUnread = unreadCount > 0;

        const timeEl = item.querySelector('[data-contact-time]');
        const previewEl = item.querySelector('[data-contact-preview]');
        const previewTextEl = item.querySelector('[data-contact-preview-text]');
        const badge = item.querySelector('[data-unread-badge]');

        if (timeEl) {
            timeEl.textContent = lastMessage.time;
            timeEl.classList.remove('hidden');
            swapClasses(timeEl, hasUnread, ['font-semibold', 'text-emerald-600'], ['text-slate-400']);
        }

        if (previewTextEl) {
            previewTextEl.textContent = lastMessage.body;
        }
        swapClasses(previewEl, hasUnread, ['font-medium', 'text-slate-700'], ['text-slate-500']);

        // Render atau hapus ikon centang secara eksplisit di DOM (hindari konflik class hidden + material-symbols-outlined)
        let readIcon = item.querySelector('[data-contact-read-icon]');
        if (lastMessage.is_sender_me) {
            if (!readIcon && previewEl) {
                readIcon = document.createElement('span');
                readIcon.setAttribute('data-contact-read-icon', '');
                readIcon.className = 'material-symbols-outlined flex-shrink-0 text-[15px] leading-none';
                previewEl.prepend(readIcon);
            }
            if (readIcon) {
                readIcon.textContent = lastMessage.is_read ? 'done_all' : 'done';
                readIcon.title = lastMessage.is_read ? 'Sudah dibaca' : 'Terkirim';
                swapClasses(readIcon, Boolean(lastMessage.is_read), ['text-blue-500'], ['text-slate-400']);
            }
        } else if (readIcon) {
            readIcon.remove();
        }

        if (badge) {
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            swapClasses(badge, hasUnread, ['inline-flex'], ['hidden']);
        }
    };

    const applyContactsSummary = (contactsSummary) => {
        if (!contactList || !Array.isArray(contactsSummary)) {
            return;
        }

        const isSearching = (searchInput?.value || '').trim() !== '';

        // Proses dari pesan terlama ke terbaru berdasarkan timestamp agar kontak paling baru di-prepend terakhir.
        const ordered = contactsSummary
            .filter((summary) => summary && summary.last_message && summary.last_message.timestamp)
            .sort((a, b) => {
                const timeA = Date.parse(a.last_message.timestamp || '') || 0;
                const timeB = Date.parse(b.last_message.timestamp || '') || 0;
                if (timeA !== timeB) {
                    return timeA - timeB;
                }
                return Number(a.last_message.id || 0) - Number(b.last_message.id || 0);
            });

        ordered.forEach((summary) => {
            // Perbarui juga snapshot defaultContactNodes agar saat reset pencarian tetap sinkron
            const defaultNode = defaultContactNodes.find(
                (n) => String(n.dataset.contactId || '') === String(summary.contact_id)
            );
            if (defaultNode) {
                updateContactItem(defaultNode, summary);
                defaultNode.dataset.lastMessageId = String(summary.last_message.id || 0);
                defaultNode.dataset.lastMessageAt = summary.last_message.timestamp || '';
            }

            const item = contactList.querySelector(`[data-contact-item][data-contact-id="${summary.contact_id}"]`);
            if (!item) {
                return;
            }

            const currentLastMessageId = Number(item.dataset.lastMessageId || 0);
            const incomingLastMessageId = Number(summary.last_message.id || 0);

            updateContactItem(item, summary);

            if (incomingLastMessageId > currentLastMessageId) {
                item.dataset.lastMessageId = String(incomingLastMessageId);
                item.dataset.lastMessageAt = summary.last_message.timestamp || '';
                if (!isSearching) {
                    contactList.prepend(item);
                }
            }
        });
    };

    // 5. Ruang Obrolan Aktif: Pengiriman Pesan AJAX & Render Bubble (Personal & Grup)
    const form = document.getElementById('chat-send-form');
    const input = document.getElementById('academic-chat-input');
    const sendBtn = document.getElementById('send-message-btn');
    const errorBox = document.getElementById('chat-ajax-error');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || form?.querySelector('input[name="_token"]')?.value;
    const fetchUrl = container?.dataset.fetchUrl;
    const currentUserId = String(container?.dataset.currentUserId || '');
    const isGroupChat = container?.dataset.isGroup === '1';

    const showError = (message) => {
        if (!errorBox) {
            return;
        }
        errorBox.querySelector('[data-error-text]').textContent = message;
        errorBox.classList.remove('hidden');
        errorBox.classList.add('flex');
    };

    const clearError = () => {
        if (!errorBox) {
            return;
        }
        errorBox.classList.add('hidden');
        errorBox.classList.remove('flex');
    };

    const getLastMessageId = () => {
        const items = container?.querySelectorAll('[data-message-id]') || [];
        if (!items.length) {
            return 0;
        }
        return Number(items[items.length - 1].dataset.messageId) || 0;
    };

    const isNearBottom = () => {
        if (!container) {
            return false;
        }
        return container.scrollHeight - container.scrollTop - container.clientHeight < 120;
    };

    const scrollToBottom = () => {
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    };

    scrollToBottom();
    requestAnimationFrame(scrollToBottom);
    window.addEventListener('load', scrollToBottom);

    const ensureDateSeparator = (dateKey, dateLabel) => {
        const separators = container.querySelectorAll('[data-date-key]');
        const lastDateKey = separators.length ? separators[separators.length - 1].dataset.dateKey : null;

        if (lastDateKey === dateKey) {
            return;
        }

        const sep = document.createElement('div');
        sep.className = 'my-3 flex items-center justify-center';
        sep.dataset.dateKey = dateKey;

        const badge = document.createElement('span');
        badge.className = 'rounded-full border border-slate-200/80 bg-white/95 px-3 py-1 text-[11px] font-medium text-slate-500 shadow-sm';
        badge.textContent = dateLabel;

        sep.appendChild(badge);
        container.appendChild(sep);
    };

    const createReplyButtonElement = () => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('data-reply-btn', '');
        btn.title = 'Balas pesan';
        btn.setAttribute('aria-label', 'Balas pesan');
        btn.className = 'flex h-7 w-7 flex-shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-400 opacity-0 transition-all hover:bg-slate-200/70 hover:text-slate-700 focus:opacity-100 group-hover:opacity-100';
        btn.innerHTML = '<span class="material-symbols-outlined text-[17px]">reply</span>';
        return btn;
    };

    const appendMessageBubble = (msg) => {
        if (container.querySelector(`[data-message-id="${msg.id}"]`)) {
            return;
        }

        document.getElementById('empty-chat-state')?.remove();
        ensureDateSeparator(msg.date_key, msg.date_label);

        const senderIdentifier = String(msg.sender_identifier ?? msg.sender_id ?? '');
        const isOwn = senderIdentifier === currentUserId;
        const isGroupMsg = isGroupChat || Boolean(msg.is_group_message);
        const resolvedSenderName = String(msg.sender_name || senderIdentifier || (isOwn ? 'Anda' : 'Pengguna'));
        const cleanSnippet = String(msg.body || '').replace(/\s+/g, ' ').trim().slice(0, 140);

        const wrapper = document.createElement('div');
        wrapper.className = `group flex items-center gap-1.5 ${isOwn ? 'justify-end' : 'justify-start'}`;
        wrapper.dataset.messageId = String(msg.id);
        wrapper.dataset.senderName = resolvedSenderName;
        wrapper.dataset.messageBody = cleanSnippet;

        if (isOwn) {
            wrapper.appendChild(createReplyButtonElement());
        }

        const bubble = document.createElement('div');
        bubble.setAttribute('data-message-bubble', '');
        bubble.className = `max-w-[82%] rounded-2xl px-4 py-2.5 shadow-sm transition-all duration-300 sm:max-w-[70%] lg:max-w-xl ${
            isOwn
                ? 'rounded-br-sm bg-blue-600 text-white'
                : 'rounded-bl-sm border border-slate-200/80 bg-white text-slate-800'
        }`;

        if (!isOwn && (isGroupMsg || msg.sender_name)) {
            const senderEl = document.createElement('div');
            senderEl.className = 'mb-1 text-xs font-semibold text-purple-700';
            senderEl.dataset.senderName = '';
            senderEl.textContent = resolvedSenderName;
            bubble.appendChild(senderEl);
        }

        if (msg.reply_to && typeof msg.reply_to === 'object' && msg.reply_to.id) {
            const quoteBtn = document.createElement('button');
            quoteBtn.type = 'button';
            quoteBtn.dataset.quoteJump = String(msg.reply_to.id);
            quoteBtn.className = `mb-2 block w-full cursor-pointer rounded-lg border-l-4 px-3 py-1.5 text-left transition ${
                isOwn
                    ? 'border-white/80 bg-blue-700/75 hover:bg-blue-700/90'
                    : 'border-blue-500 bg-slate-100/90 hover:bg-slate-200/75'
            }`;

            const quoteSenderEl = document.createElement('div');
            quoteSenderEl.className = `truncate text-[11px] font-semibold ${isOwn ? 'text-white' : 'text-blue-700'}`;
            quoteSenderEl.textContent = msg.reply_to.sender_name || 'Pesan';

            const quoteBodyEl = document.createElement('p');
            quoteBodyEl.className = `mt-0.5 line-clamp-2 text-xs ${isOwn ? 'text-blue-100' : 'text-slate-600'}`;
            quoteBodyEl.textContent = msg.reply_to.body || '';

            quoteBtn.appendChild(quoteSenderEl);
            quoteBtn.appendChild(quoteBodyEl);
            bubble.appendChild(quoteBtn);
        }

        const bodyEl = document.createElement('p');
        bodyEl.className = 'text-sm leading-relaxed whitespace-pre-line break-words';
        bodyEl.dataset.body = '';
        bodyEl.textContent = msg.body;

        const metaEl = document.createElement('div');
        metaEl.className = `mt-1 flex items-center justify-end gap-1 ${isOwn ? 'text-blue-100' : 'text-slate-400'}`;

        const timeEl = document.createElement('span');
        timeEl.className = 'text-[11px]';
        timeEl.dataset.time = '';
        timeEl.textContent = msg.time;

        metaEl.appendChild(timeEl);

        if (isOwn && !isGroupMsg) {
            const readIcon = document.createElement('span');
            readIcon.dataset.readIcon = '';
            readIcon.className = `material-symbols-outlined text-[15px] leading-none ${
                msg.is_read ? 'text-emerald-300' : 'text-blue-200'
            }`;
            readIcon.title = msg.is_read ? 'Sudah dibaca' : 'Terkirim';
            readIcon.textContent = msg.is_read ? 'done_all' : 'done';
            metaEl.appendChild(readIcon);
        }

        bubble.appendChild(bodyEl);
        bubble.appendChild(metaEl);
        wrapper.appendChild(bubble);

        if (!isOwn) {
            wrapper.appendChild(createReplyButtonElement());
        }

        container.appendChild(wrapper);
    };

    // Event Delegation pada #chat-messages-container: Tombol "Balas" & Klik Kutipan untuk Lompat ke Pesan Asli
    container?.addEventListener('click', (event) => {
        const replyBtn = event.target.closest('[data-reply-btn]');
        if (replyBtn) {
            const msgWrapper = replyBtn.closest('[data-message-id]');
            if (!msgWrapper) {
                return;
            }
            const msgId = msgWrapper.dataset.messageId || '';
            const senderName = msgWrapper.dataset.senderName
                || msgWrapper.querySelector('[data-sender-name]')?.textContent?.trim()
                || 'Pesan';
            const msgBody = msgWrapper.dataset.messageBody
                || msgWrapper.querySelector('[data-body]')?.textContent?.replace(/\s+/g, ' ').trim().slice(0, 140)
                || '';
            activateReplyState(msgId, senderName, msgBody);
            return;
        }

        const quoteJumpBtn = event.target.closest('[data-quote-jump]');
        if (quoteJumpBtn) {
            const targetId = quoteJumpBtn.dataset.quoteJump;
            if (!targetId) {
                return;
            }
            const targetWrapper = container.querySelector(`[data-message-id="${targetId}"]`);
            if (!targetWrapper) {
                return;
            }
            targetWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const targetBubble = targetWrapper.querySelector('[data-message-bubble]') || targetWrapper.firstElementChild;
            if (targetBubble) {
                targetBubble.classList.add('ring-4', 'ring-amber-400/80', 'scale-[1.01]');
                setTimeout(() => {
                    targetBubble.classList.remove('ring-4', 'ring-amber-400/80', 'scale-[1.01]');
                }, 1500);
            }
        }
    });

    const updateReadReceipts = (lastReadId) => {
        if (!lastReadId || isGroupChat) {
            return;
        }

        container.querySelectorAll('[data-message-id]').forEach((el) => {
            if (Number(el.dataset.messageId) <= lastReadId) {
                const icon = el.querySelector('[data-read-icon]');
                if (icon && icon.textContent !== 'done_all') {
                    icon.textContent = 'done_all';
                    icon.title = 'Sudah dibaca';
                    icon.classList.remove('text-blue-200');
                    icon.classList.add('text-emerald-300');
                }
            }
        });
    };

    if (container && form && input && sendBtn) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (sendBtn.disabled) {
                return;
            }

            const body = input.value.trim();
            if (!body) {
                return;
            }

            clearError();
            sendBtn.disabled = true;

            try {
                const replyToIdValue = replyToIdInput?.value ? Number(replyToIdInput.value) : null;
                const payload = { body };
                if (replyToIdValue) {
                    payload.reply_to_id = replyToIdValue;
                }

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                if (response.status === 401 || response.status === 419) {
                    window.location.reload();
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    const errorMessage = data?.errors?.body?.[0] || data?.errors?.reply_to_id?.[0] || data?.message || 'Gagal mengirim pesan.';
                    showError(errorMessage);
                    return;
                }

                appendMessageBubble(data.message);
                applyContactsSummary(data.contacts_summary);
                clearReplyState();
                input.value = '';
                input.focus();
                scrollToBottom();
            } catch (err) {
                console.error(err);
                showError('Gagal mengirim pesan. Periksa koneksi jaringan Anda.');
            } finally {
                sendBtn.disabled = false;
            }
        });
    }

    // 6. Pagination Riwayat Pesan ("Muat pesan sebelumnya" - 50 pesan per muatan)
    const loadMoreWrapper = document.getElementById('load-more-messages-wrapper');
    const loadMoreBtn = document.getElementById('load-more-messages-btn');

    const buildMessageBubbleElement = (msg) => {
        const senderIdentifier = String(msg.sender_identifier ?? msg.sender_id ?? '');
        const isOwn = senderIdentifier === currentUserId;
        const isGroupMsg = isGroupChat || Boolean(msg.is_group_message);
        const resolvedSenderName = String(msg.sender_name || senderIdentifier || (isOwn ? 'Anda' : 'Pengguna'));
        const cleanSnippet = String(msg.body || '').replace(/\s+/g, ' ').trim().slice(0, 140);

        const wrapper = document.createElement('div');
        wrapper.className = `group flex items-center gap-1.5 ${isOwn ? 'justify-end' : 'justify-start'}`;
        wrapper.dataset.messageId = String(msg.id);
        wrapper.dataset.senderName = resolvedSenderName;
        wrapper.dataset.messageBody = cleanSnippet;

        if (isOwn) {
            wrapper.appendChild(createReplyButtonElement());
        }

        const bubble = document.createElement('div');
        bubble.setAttribute('data-message-bubble', '');
        bubble.className = `max-w-[82%] rounded-2xl px-4 py-2.5 shadow-sm transition-all duration-300 sm:max-w-[70%] lg:max-w-xl ${
            isOwn
                ? 'rounded-br-sm bg-blue-600 text-white'
                : 'rounded-bl-sm border border-slate-200/80 bg-white text-slate-800'
        }`;

        if (!isOwn && (isGroupMsg || msg.sender_name)) {
            const senderEl = document.createElement('div');
            senderEl.className = 'mb-1 text-xs font-semibold text-purple-700';
            senderEl.dataset.senderName = '';
            senderEl.textContent = resolvedSenderName;
            bubble.appendChild(senderEl);
        }

        if (msg.reply_to && typeof msg.reply_to === 'object' && msg.reply_to.id) {
            const quoteBtn = document.createElement('button');
            quoteBtn.type = 'button';
            quoteBtn.dataset.quoteJump = String(msg.reply_to.id);
            quoteBtn.className = `mb-2 block w-full cursor-pointer rounded-lg border-l-4 px-3 py-1.5 text-left transition ${
                isOwn
                    ? 'border-white/80 bg-blue-700/75 hover:bg-blue-700/90'
                    : 'border-blue-500 bg-slate-100/90 hover:bg-slate-200/75'
            }`;

            const quoteSenderEl = document.createElement('div');
            quoteSenderEl.className = `truncate text-[11px] font-semibold ${isOwn ? 'text-white' : 'text-blue-700'}`;
            quoteSenderEl.textContent = msg.reply_to.sender_name || 'Pesan';

            const quoteBodyEl = document.createElement('p');
            quoteBodyEl.className = `mt-0.5 line-clamp-2 text-xs ${isOwn ? 'text-blue-100' : 'text-slate-600'}`;
            quoteBodyEl.textContent = msg.reply_to.body || '';

            quoteBtn.appendChild(quoteSenderEl);
            quoteBtn.appendChild(quoteBodyEl);
            bubble.appendChild(quoteBtn);
        }

        const bodyEl = document.createElement('p');
        bodyEl.className = 'text-sm leading-relaxed whitespace-pre-line break-words';
        bodyEl.dataset.body = '';
        bodyEl.textContent = msg.body;

        const metaEl = document.createElement('div');
        metaEl.className = `mt-1 flex items-center justify-end gap-1 ${isOwn ? 'text-blue-100' : 'text-slate-400'}`;

        const timeEl = document.createElement('span');
        timeEl.className = 'text-[11px]';
        timeEl.dataset.time = '';
        timeEl.textContent = msg.time;

        metaEl.appendChild(timeEl);

        if (isOwn && !isGroupMsg) {
            const readIcon = document.createElement('span');
            readIcon.dataset.readIcon = '';
            readIcon.className = `material-symbols-outlined text-[15px] leading-none ${
                msg.is_read ? 'text-emerald-300' : 'text-blue-200'
            }`;
            readIcon.title = msg.is_read ? 'Sudah dibaca' : 'Terkirim';
            readIcon.textContent = msg.is_read ? 'done_all' : 'done';
            metaEl.appendChild(readIcon);
        }

        bubble.appendChild(bodyEl);
        bubble.appendChild(metaEl);
        wrapper.appendChild(bubble);

        if (!isOwn) {
            wrapper.appendChild(createReplyButtonElement());
        }

        return wrapper;
    };

    loadMoreBtn?.addEventListener('click', async () => {
        if (!container || !fetchUrl || loadMoreBtn.disabled) {
            return;
        }

        const firstMsgEl = container.querySelector('[data-message-id]');
        const oldestId = firstMsgEl ? Number(firstMsgEl.dataset.messageId || 0) : 0;
        if (!oldestId) {
            loadMoreWrapper?.classList.add('hidden');
            return;
        }

        const labelEl = loadMoreBtn.querySelector('[data-load-more-text]');
        const originalLabel = labelEl?.textContent || 'Muat pesan sebelumnya';
        loadMoreBtn.disabled = true;
        if (labelEl) {
            labelEl.textContent = 'Memuat...';
        }

        const prevScrollHeight = container.scrollHeight;
        const prevScrollTop = container.scrollTop;

        try {
            const data = await fetchJson(`${fetchUrl}?before_id=${oldestId}`);
            if (!data || !Array.isArray(data.messages) || data.messages.length === 0) {
                loadMoreWrapper?.classList.add('hidden');
                loadMoreWrapper?.classList.remove('flex');
                return;
            }

            const fragment = document.createDocumentFragment();
            let lastBatchDateKey = null;

            data.messages.forEach((msg) => {
                if (container.querySelector(`[data-message-id="${msg.id}"]`)) {
                    return;
                }
                if (msg.date_key && msg.date_key !== lastBatchDateKey) {
                    const existingSep = container.querySelector(`[data-date-key="${msg.date_key}"]`);
                    if (existingSep) {
                        existingSep.remove();
                    }
                    const sep = document.createElement('div');
                    sep.className = 'my-3 flex items-center justify-center';
                    sep.dataset.dateKey = msg.date_key;
                    const badge = document.createElement('span');
                    badge.className = 'rounded-full border border-slate-200/80 bg-white/95 px-3 py-1 text-[11px] font-medium text-slate-500 shadow-sm';
                    badge.textContent = msg.date_label;
                    sep.appendChild(badge);
                    fragment.appendChild(sep);
                    lastBatchDateKey = msg.date_key;
                }
                fragment.appendChild(buildMessageBubbleElement(msg));
            });

            if (loadMoreWrapper && loadMoreWrapper.nextSibling) {
                container.insertBefore(fragment, loadMoreWrapper.nextSibling);
            } else {
                container.prepend(fragment);
            }

            if (!data.has_more) {
                loadMoreWrapper?.classList.add('hidden');
                loadMoreWrapper?.classList.remove('flex');
            }

            // Pertahankan posisi scroll agar layar tidak melompat ke atas
            container.scrollTop = container.scrollHeight - prevScrollHeight + prevScrollTop;
        } catch (err) {
            console.error('Gagal memuat pesan sebelumnya:', err);
        } finally {
            loadMoreBtn.disabled = false;
            if (labelEl) {
                labelEl.textContent = originalLabel;
            }
        }
    });

    // 7. Laravel Echo (Reverb WebSockets) Real-Time Listener & Indikator Status Koneksi Jujur (Tanpa Polling 3 Detik)
    const wsBadge = document.getElementById('ws-connection-badge');
    const wsDot = wsBadge?.querySelector('[data-ws-dot]');
    const wsLabel = wsBadge?.querySelector('[data-ws-label]');
    const sidebarWsDot = document.getElementById('sidebar-ws-dot');

    const updateWsConnectionBadge = (state) => {
        const normalized = String(state || '').toLowerCase();

        // Sinkronkan juga titik status WebSocket di avatar profil sendiri pada sidebar
        if (sidebarWsDot) {
            sidebarWsDot.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-red-500', 'animate-pulse');
            if (normalized === 'connected') {
                sidebarWsDot.classList.add('bg-emerald-500');
                sidebarWsDot.title = 'Status WebSocket: Terhubung';
            } else if (normalized === 'connecting' || normalized === 'initialized') {
                sidebarWsDot.classList.add('bg-amber-500', 'animate-pulse');
                sidebarWsDot.title = 'Status WebSocket: Menghubungkan...';
            } else {
                sidebarWsDot.classList.add('bg-red-500');
                sidebarWsDot.title = 'Status WebSocket: Terputus';
            }
        }

        if (!wsBadge || !wsDot || !wsLabel) {
            return;
        }

        wsBadge.dataset.wsStatus = normalized;

        wsBadge.classList.remove(
            'bg-emerald-50',
            'text-emerald-700',
            'bg-amber-50',
            'text-amber-700',
            'bg-red-50',
            'text-red-700'
        );
        wsDot.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-red-500', 'animate-pulse');

        if (normalized === 'connected') {
            wsBadge.classList.add('bg-emerald-50', 'text-emerald-700');
            wsDot.classList.add('bg-emerald-500');
            wsLabel.textContent = 'Terhubung';
        } else if (normalized === 'connecting' || normalized === 'initialized') {
            wsBadge.classList.add('bg-amber-50', 'text-amber-700');
            wsDot.classList.add('bg-amber-500', 'animate-pulse');
            wsLabel.textContent = 'Menghubungkan...';
        } else {
            wsBadge.classList.add('bg-red-50', 'text-red-700');
            wsDot.classList.add('bg-red-500');
            wsLabel.textContent = 'Terputus';
        }
    };

    const fetchJson = async (url) => {
        if (!url) {
            return null;
        }
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
            },
        });

        return response.ok ? response.json() : null;
    };

    const refreshContactsSummaryOnce = async () => {
        if (!summaryUrl) {
            return;
        }
        try {
            const data = await fetchJson(summaryUrl);
            if (data && Array.isArray(data.contacts_summary)) {
                applyContactsSummary(data.contacts_summary);
            }
        } catch (err) {
            // Abaikan error jaringan sementara
        }
    };

    if (window.Echo) {
        const pusherConnection = window.Echo.connector?.pusher?.connection;

        if (pusherConnection) {
            updateWsConnectionBadge(pusherConnection.state || 'connecting');

            pusherConnection.bind('state_change', (states) => {
                const currentState = states?.current || pusherConnection.state;
                updateWsConnectionBadge(currentState);
                if (currentState === 'connected') {
                    refreshContactsSummaryOnce();
                }
            });

            pusherConnection.bind('connected', () => updateWsConnectionBadge('connected'));
            pusherConnection.bind('connecting', () => updateWsConnectionBadge('connecting'));
            pusherConnection.bind('disconnected', () => updateWsConnectionBadge('disconnected'));
            pusherConnection.bind('unavailable', () => updateWsConnectionBadge('unavailable'));
            pusherConnection.bind('failed', () => updateWsConnectionBadge('failed'));
        } else {
            updateWsConnectionBadge('disconnected');
        }

        // Listener Real-Time untuk Ruang Obrolan yang Sedang Dibuka (Grup maupun DM 1-on-1)
        if (activeWsGroupId) {
            const handleIncomingActiveChatBroadcast = async (e) => {
                if (!activeReceiverId || !isElementVisible(activeChatWrapper)) {
                    return;
                }

                const incomingMsg = e?.message || e;
                if (!incomingMsg || !incomingMsg.id) {
                    return;
                }

                const senderId = String(incomingMsg.sender_identifier ?? incomingMsg.sender_id ?? '');
                if (senderId === currentUserId || container?.querySelector(`[data-message-id="${incomingMsg.id}"]`)) {
                    return;
                }

                appendMessageBubble(incomingMsg);
                scrollToBottom();

                // Tandai pesan di ruang obrolan yang sedang dibuka sebagai sudah dibaca (perbarui last_read_message_id / is_read)
                const activeFetchUrl = container?.dataset.fetchUrl || '';
                if (activeFetchUrl && activeReceiverId) {
                    const readData = await fetchJson(`${activeFetchUrl}?mark_read=1`);
                    if (readData && Array.isArray(readData.contacts_summary)) {
                        applyContactsSummary(readData.contacts_summary);
                    }
                }
            };

            window.Echo.channel(`group.${activeWsGroupId}`)
                .listen('MessageSent', handleIncomingActiveChatBroadcast)
                .listen('.MessageSent', handleIncomingActiveChatBroadcast);
        }

        // Listener Event-Driven untuk Pembaruan Sidebar & Unread Badge (Tanpa Polling 3 Detik)
        const handleSidebarBroadcast = () => {
            refreshContactsSummaryOnce();
        };

        window.Echo.channel('sidebar.updates')
            .listen('MessageSent', handleSidebarBroadcast)
            .listen('.MessageSent', handleSidebarBroadcast);
    } else {
        updateWsConnectionBadge('disconnected');
    }

    // Segarkan ringkasan sidebar hanya saat pengguna kembali mengaktifkan tab browser
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshContactsSummaryOnce();
        }
    });
});
