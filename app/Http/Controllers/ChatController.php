<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatDirectoryService;
use App\Services\Chat\ChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private ChatDirectoryService $directoryService,
        private ChatMessageService $messageService,
    ) {}

    /**
     * Tampilkan halaman dashboard komunikasi beserta daftar kontak sivitas akademika.
     */
    public function index(Request $request): View
    {
        $currentUser = $this->directoryService->currentSsoUser();
        $this->directoryService->ensureUserKrsSynced($currentUser);

        $requestedCategory = (string) $request->query('category', 'all');
        $sidebarData = $this->directoryService->buildSidebarData($currentUser, null, $requestedCategory);
        $kontak = $sidebarData['kontak'];
        $categoryCounts = $sidebarData['categoryCounts'];
        $hasMoreContacts = $sidebarData['hasMoreContacts'];
        $loadedCategoryCount = $sidebarData['loadedCategoryCount'];
        $tahunAktif = $this->directoryService->getActiveAcademicYear();

        return view('dashboard', compact(
            'kontak',
            'tahunAktif',
            'categoryCounts',
            'hasMoreContacts',
            'loadedCategoryCount'
        ));
    }

    /**
     * Tampilkan riwayat obrolan antara pengguna yang login dan kontak/grup terpilih.
     */
    public function show(Request $request, string $receiver_id): View|RedirectResponse
    {
        $currentUser = $this->directoryService->currentSsoUser();
        $this->directoryService->ensureUserKrsSynced($currentUser);
        $userId = (string) $currentUser->id;
        $requestedCategory = (string) $request->query('category', 'all');

        if ($receiver_id === $userId) {
            return redirect()->route('dashboard');
        }

        if ($this->directoryService->isGroupIdentifier($receiver_id)) {
            $groupId = $this->directoryService->extractGroupId($receiver_id);
            $target = $this->directoryService->resolveGroupChatTarget($groupId, $currentUser);
            $group = $target['group'];
            $groupMembers = $target['groupMembers'];
            $receiver = $target['receiver'];

            $history = $this->messageService->loadGroupHistory($group, $currentUser, $target['directory']);
            $messages = $history['messages'];
            $hasMoreMessages = $history['hasMoreMessages'];

            $sidebarData = $this->directoryService->buildSidebarData($currentUser, $receiver, $requestedCategory);
            $kontak = $sidebarData['kontak'];
            $categoryCounts = $sidebarData['categoryCounts'];
            $hasMoreContacts = $sidebarData['hasMoreContacts'];
            $loadedCategoryCount = $sidebarData['loadedCategoryCount'];
            $tahunAktif = $this->directoryService->getActiveAcademicYear();

            return view('dashboard', compact(
                'kontak',
                'messages',
                'receiver',
                'tahunAktif',
                'group',
                'groupMembers',
                'hasMoreMessages',
                'categoryCounts',
                'hasMoreContacts',
                'loadedCategoryCount'
            ));
        }

        $receiver = $this->directoryService->resolveDirectReceiver($receiver_id);
        $history = $this->messageService->loadDirectHistory($receiver_id, $currentUser);
        $messages = $history['messages'];
        $hasMoreMessages = $history['hasMoreMessages'];

        $sidebarData = $this->directoryService->buildSidebarData($currentUser, $receiver, $requestedCategory);
        $kontak = $sidebarData['kontak'];
        $categoryCounts = $sidebarData['categoryCounts'];
        $hasMoreContacts = $sidebarData['hasMoreContacts'];
        $loadedCategoryCount = $sidebarData['loadedCategoryCount'];
        $tahunAktif = $this->directoryService->getActiveAcademicYear();

        return view('dashboard', compact(
            'kontak',
            'messages',
            'receiver',
            'tahunAktif',
            'hasMoreMessages',
            'categoryCounts',
            'hasMoreContacts',
            'loadedCategoryCount'
        ));
    }

    /**
     * Simpan pesan baru ke tabel messages atau group_messages lalu kembalikan JSON (AJAX) atau redirect.
     */
    public function sendMessage(Request $request, string $receiver_id): RedirectResponse|JsonResponse
    {
        $currentUser = $this->directoryService->currentSsoUser();
        $userId = (string) $currentUser->id;

        if ($receiver_id === $userId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tidak dapat mengirim pesan ke diri sendiri.',
                ], 422);
            }

            return redirect()->route('dashboard');
        }

        $isGroupTarget = $this->directoryService->isGroupIdentifier($receiver_id);
        $replyTable = $isGroupTarget ? 'group_messages' : 'messages';

        $validated = $request->validate([
            'body'        => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:'.$replyTable.',id'],
        ], [
            'body.required'      => 'Pesan tidak boleh kosong.',
            'body.max'           => 'Pesan maksimal 5000 karakter.',
            'reply_to_id.exists' => 'Pesan yang dibalas tidak ditemukan.',
        ]);

        $replyToId = ! empty($validated['reply_to_id']) ? (int) $validated['reply_to_id'] : null;

        if ($isGroupTarget) {
            $groupId = $this->directoryService->extractGroupId($receiver_id);
            $group = $this->directoryService->findAuthorizedGroup($groupId, $userId);
            $formattedMessage = $this->messageService->sendGroupMessage($group, $currentUser, $validated['body'], $replyToId);
        } else {
            $formattedMessage = $this->messageService->sendDirectMessage($receiver_id, $currentUser, $validated['body'], $replyToId);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'          => $formattedMessage,
                'contacts_summary' => $this->directoryService->getContactsSummary($userId)->values(),
            ], 201);
        }

        return redirect()->route('chat.show', $receiver_id);
    }

    /**
     * Ambil pesan baru setelah ID terakhir, pesan lama sebelum ID, status pesan terbaca, dan ringkasan sidebar.
     */
    public function fetchMessages(Request $request, string $receiver_id): JsonResponse
    {
        $currentUser = $this->directoryService->currentSsoUser();
        $userId = (string) $currentUser->id;

        if ($receiver_id === $userId) {
            return response()->json([
                'messages'         => [],
                'last_read_id'     => 0,
                'contacts_summary' => $this->directoryService->getContactsSummary($userId)->values(),
            ]);
        }

        $afterId = max(0, (int) $request->query('after_id', 0));
        $beforeId = max(0, (int) $request->query('before_id', 0));
        $markReadOnly = $request->boolean('mark_read');

        if ($this->directoryService->isGroupIdentifier($receiver_id)) {
            $groupId = $this->directoryService->extractGroupId($receiver_id);
            $group = $this->directoryService->findAuthorizedGroup($groupId, $userId);

            return response()->json(
                $this->messageService->fetchGroupMessagesPayload($group, $currentUser, $afterId, $beforeId, $markReadOnly)
            );
        }

        return response()->json(
            $this->messageService->fetchDirectMessagesPayload($receiver_id, $currentUser, $afterId, $beforeId, $markReadOnly)
        );
    }

    /**
     * Ambil ringkasan sidebar (unread count & pesan terakhir) untuk sinkronisasi halaman dashboard.
     */
    public function contactsSummary(): JsonResponse
    {
        $currentUser = $this->directoryService->currentSsoUser();

        return response()->json([
            'contacts_summary' => $this->directoryService->getContactsSummary((string) $currentUser->id)->values(),
        ]);
    }

    /**
     * Pencarian AJAX & Lazy-Load Tab Kategori (Dosen / Mahasiswa) di Sidebar dengan pengurutan Top Relevance Match.
     */
    public function search(Request $request): JsonResponse
    {
        $currentUser = $this->directoryService->currentSsoUser();
        $keyword = trim((string) $request->query('keyword', ''));
        $roleFilter = trim((string) $request->query('role', 'all'));
        $offset = max(0, (int) $request->query('offset', 0));
        $limit = min(60, max(10, (int) $request->query('limit', 40)));

        return response()->json(
            $this->directoryService->searchContacts($currentUser, $keyword, $roleFilter, $offset, $limit)
        );
    }
}
