window.appendQuickText = function appendQuickText(text) {
    const input = document.getElementById('academic-chat-input');
    if (input) {
        input.value = text;
        input.focus();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const POLL_INTERVAL_MS = 3000;

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

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // 2. Pencarian & Filter Kontak Real-Time di Sidebar
    const searchInput = document.getElementById('contact-search-input');
    const filterButtons = document.querySelectorAll('[data-role-filter]');
    const contactItems = document.querySelectorAll('[data-contact-item]');
    const searchEmptyState = document.getElementById('contact-search-empty');
    let activeRoleFilter = 'all';

    const applyContactFilter = () => {
        const keyword = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        contactItems.forEach((item) => {
            const role = item.dataset.contactRole || '';
            const searchContent = item.dataset.contactSearch || '';
            const matchesRole = activeRoleFilter === 'all' || role === activeRoleFilter;
            const matchesKeyword = keyword === '' || searchContent.includes(keyword);
            const isVisible = matchesRole && matchesKeyword;

            item.classList.toggle('hidden', !isVisible);
            if (isVisible) {
                visibleCount++;
            }
        });

        if (searchEmptyState && contactItems.length > 0) {
            searchEmptyState.classList.toggle('hidden', visibleCount > 0);
        }
    };

    searchInput?.addEventListener('input', applyContactFilter);

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeRoleFilter = button.dataset.roleFilter || 'all';

            filterButtons.forEach((btn) => {
                const isActive = btn === button;
                btn.classList.toggle('bg-blue-600', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('bg-slate-100', !isActive);
                btn.classList.toggle('text-slate-600', !isActive);
                btn.classList.toggle('hover:bg-slate-200', !isActive);
            });

            applyContactFilter();
        });
    });

    // 3. Tombol Respon Cepat
    document.querySelectorAll('[data-quick-text]').forEach((chip) => {
        chip.addEventListener('click', () => {
            window.appendQuickText(chip.dataset.quickText || '');
        });
    });

    // 4. Sinkronisasi Sidebar: Unread Badge, Preview Pesan Terakhir & Auto-Reorder
    const contactList = document.getElementById('contact-list-container');
    const summaryUrl = contactList?.dataset.summaryUrl;
    const activeContactId = Number(contactList?.dataset.activeContactId || 0);

    const swapClasses = (el, isOn, onClasses, offClasses) => {
        if (!el) {
            return;
        }
        onClasses.forEach((cls) => el.classList.toggle(cls, isOn));
        offClasses.forEach((cls) => el.classList.toggle(cls, !isOn));
    };

    const updateContactItem = (item, summary) => {
        const lastMessage = summary.last_message;
        const isActiveContact = Number(summary.contact_id) === activeContactId;
        const unreadCount = isActiveContact ? 0 : Number(summary.unread_count || 0);
        const hasUnread = unreadCount > 0;

        const timeEl = item.querySelector('[data-contact-time]');
        const roleChip = item.querySelector('[data-contact-role-chip]');
        const previewEl = item.querySelector('[data-contact-preview]');
        const previewTextEl = item.querySelector('[data-contact-preview-text]');
        const readIcon = item.querySelector('[data-contact-read-icon]');
        const badge = item.querySelector('[data-unread-badge]');

        if (timeEl) {
            timeEl.textContent = lastMessage.time;
            timeEl.classList.remove('hidden');
            swapClasses(timeEl, hasUnread, ['font-semibold', 'text-emerald-600'], ['text-slate-400']);
        }

        roleChip?.classList.add('hidden');

        if (previewTextEl) {
            previewTextEl.textContent = lastMessage.body;
        }
        swapClasses(previewEl, hasUnread, ['font-medium', 'text-slate-700'], ['text-slate-500']);

        if (readIcon) {
            readIcon.classList.toggle('hidden', !lastMessage.is_sender_me);
            if (lastMessage.is_sender_me) {
                readIcon.textContent = lastMessage.is_read ? 'done_all' : 'done';
                readIcon.title = lastMessage.is_read ? 'Sudah dibaca' : 'Terkirim';
                swapClasses(readIcon, lastMessage.is_read, ['text-blue-500'], ['text-slate-400']);
            }
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

        // Proses dari pesan terlama ke terbaru agar kontak paling baru di-prepend terakhir (berada di urutan teratas).
        const ordered = contactsSummary
            .filter((summary) => summary && summary.last_message)
            .sort((a, b) => Number(a.last_message.id) - Number(b.last_message.id));

        ordered.forEach((summary) => {
            const item = contactList.querySelector(`[data-contact-item][data-contact-id="${summary.contact_id}"]`);
            if (!item) {
                return;
            }

            const currentLastMessageId = Number(item.dataset.lastMessageId || 0);
            const incomingLastMessageId = Number(summary.last_message.id);

            updateContactItem(item, summary);

            if (incomingLastMessageId > currentLastMessageId) {
                item.dataset.lastMessageId = String(incomingLastMessageId);
                item.dataset.lastMessageAt = summary.last_message.timestamp || '';
                contactList.prepend(item);
            }
        });
    };

    // 5. Ruang Obrolan Aktif: Pengiriman Pesan AJAX & Render Bubble
    const container = document.getElementById('chat-messages-container');
    const form = document.getElementById('chat-send-form');
    const input = document.getElementById('academic-chat-input');
    const sendBtn = document.getElementById('send-message-btn');
    const errorBox = document.getElementById('chat-ajax-error');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || form?.querySelector('input[name="_token"]')?.value;
    const fetchUrl = container?.dataset.fetchUrl;
    const currentUserId = Number(container?.dataset.currentUserId || 0);

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

    const appendMessageBubble = (msg) => {
        if (container.querySelector(`[data-message-id="${msg.id}"]`)) {
            return;
        }

        document.getElementById('empty-chat-state')?.remove();
        ensureDateSeparator(msg.date_key, msg.date_label);

        const isOwn = Number(msg.sender_id) === currentUserId;
        const wrapper = document.createElement('div');
        wrapper.className = `flex ${isOwn ? 'justify-end' : 'justify-start'}`;
        wrapper.dataset.messageId = msg.id;

        const bubble = document.createElement('div');
        bubble.className = `max-w-[82%] rounded-2xl px-4 py-2.5 shadow-sm sm:max-w-[70%] lg:max-w-xl ${
            isOwn
                ? 'rounded-br-sm bg-blue-600 text-white'
                : 'rounded-bl-sm border border-slate-200/80 bg-white text-slate-800'
        }`;

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

        if (isOwn) {
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
        container.appendChild(wrapper);
    };

    const updateReadReceipts = (lastReadId) => {
        if (!lastReadId) {
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
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ body }),
                });

                if (response.status === 401 || response.status === 419) {
                    window.location.reload();
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    const errorMessage = data?.errors?.body?.[0] || data?.message || 'Gagal mengirim pesan.';
                    showError(errorMessage);
                    return;
                }

                appendMessageBubble(data.message);
                applyContactsSummary(data.contacts_summary);
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

    // 6. Polling Global: berjalan di /dashboard maupun saat membuka ruang obrolan
    let isPolling = false;

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
            },
        });

        return response.ok ? response.json() : null;
    };

    const pollActiveChat = async () => {
        const shouldScroll = isNearBottom();
        const data = await fetchJson(`${fetchUrl}?after_id=${getLastMessageId()}`);

        if (!data) {
            return;
        }

        if (Array.isArray(data.messages) && data.messages.length > 0) {
            data.messages.forEach(appendMessageBubble);
            if (shouldScroll) {
                scrollToBottom();
            }
        }

        updateReadReceipts(data.last_read_id);
        applyContactsSummary(data.contacts_summary);
    };

    const pollContactsSummary = async () => {
        const data = await fetchJson(summaryUrl);

        if (data) {
            applyContactsSummary(data.contacts_summary);
        }
    };

    const poll = async () => {
        if (isPolling || document.hidden) {
            return;
        }
        isPolling = true;

        try {
            if (container && fetchUrl) {
                await pollActiveChat();
            } else if (summaryUrl) {
                await pollContactsSummary();
            }
        } catch (err) {
            console.error('Polling gagal:', err);
        } finally {
            isPolling = false;
        }
    };

    if ((container && fetchUrl) || summaryUrl) {
        setInterval(poll, POLL_INTERVAL_MS);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                poll();
            }
        });
    }
});
