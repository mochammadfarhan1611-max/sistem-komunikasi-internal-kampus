<?php

namespace App\Services\Chat;

use App\Events\MessageSent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\User;
use App\Services\UnujaPortalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatMessageService
{
    public function __construct(
        private UnujaPortalService $portalService,
        private ChatDirectoryService $directoryService,
    ) {}

    /**
     * Muat maksimal 50 pesan terbaru pada ruang obrolan grup dan perbarui status baca anggota.
     *
     * @param  array<string, array<string, mixed>>  $directory
     * @return array{messages: SupportCollection<int, GroupMessage>, hasMoreMessages: bool}
     */
    public function loadGroupHistory(Group $group, User $currentUser, array $directory): array
    {
        $this->markGroupRead($group->id, (string) $currentUser->id);

        $rawGroupMessages = GroupMessage::query()
            ->with('replyTo')
            ->where('group_id', $group->id)
            ->latest('id')
            ->take(51)
            ->get();

        $hasMoreMessages = $rawGroupMessages->count() > 50;
        $messages = $rawGroupMessages
            ->take(50)
            ->reverse()
            ->values()
            ->each(function (GroupMessage $msg) use ($directory, $currentUser): void {
                $senderProfile = $this->directoryService->resolveSenderProfile((string) $msg->sender_identifier, $directory, $currentUser);
                $senderObj = $this->portalService->toUserObject($senderProfile);

                $msg->setAttribute('sender_id', $msg->sender_identifier);
                $msg->setAttribute('sender_name', $senderObj->nama_lengkap);
                $msg->setAttribute('is_group_message', true);
                $msg->setAttribute('is_read', false);
                $msg->setAttribute('reply_to_payload', $this->formatReplyToPayload($msg->replyTo, $directory, $currentUser));
                $msg->setRelation('sender', $senderObj);
            });

        return [
            'messages'        => $messages,
            'hasMoreMessages' => $hasMoreMessages,
        ];
    }

    /**
     * Muat maksimal 50 pesan terbaru pada chat pribadi (DM) dan tandai pesan masuk sebagai sudah dibaca.
     *
     * @return array{messages: SupportCollection<int, Message>, hasMoreMessages: bool}
     */
    public function loadDirectHistory(string $receiverId, User $currentUser): array
    {
        $userId = (string) $currentUser->id;
        $directory = $this->portalService->getDirectoryContacts();

        $rawDirectMessages = Message::query()
            ->with('replyTo')
            ->where(function (Builder $query) use ($userId, $receiverId): void {
                $query->where(function (Builder $q) use ($userId, $receiverId): void {
                    $q->where('sender_identifier', $userId)->where('receiver_identifier', $receiverId);
                })->orWhere(function (Builder $q) use ($userId, $receiverId): void {
                    $q->where('sender_identifier', $receiverId)->where('receiver_identifier', $userId);
                });
            })
            ->latest('id')
            ->take(51)
            ->get();

        $hasMoreMessages = $rawDirectMessages->count() > 50;
        $messages = $rawDirectMessages
            ->take(50)
            ->reverse()
            ->values()
            ->each(function (Message $msg) use ($directory, $currentUser): void {
                $msg->setAttribute('sender_id', $msg->sender_identifier);
                $msg->setAttribute('receiver_id', $msg->receiver_identifier);

                $senderProfile = $this->directoryService->resolveSenderProfile((string) $msg->sender_identifier, $directory, $currentUser);
                $senderObj = $this->portalService->toUserObject($senderProfile);
                $msg->setAttribute('sender_name', $senderObj->nama_lengkap);
                $msg->setAttribute('reply_to_payload', $this->formatReplyToPayload($msg->replyTo, $directory, $currentUser));
                $msg->setRelation('sender', $senderObj);
            });

        $this->markDirectMessagesRead($receiverId, $userId);

        return [
            'messages'        => $messages,
            'hasMoreMessages' => $hasMoreMessages,
        ];
    }

    /**
     * Simpan pesan grup baru, perbarui last_read_message_id, dan kirim broadcast real-time Reverb.
     *
     * @return array<string, mixed>
     */
    public function sendGroupMessage(Group $group, User $currentUser, string $body, ?int $replyToId): array
    {
        $userId = (string) $currentUser->id;

        if ($replyToId !== null) {
            $belongsToSameGroup = GroupMessage::where('id', $replyToId)->where('group_id', $group->id)->exists();
            if (! $belongsToSameGroup) {
                $replyToId = null;
            }
        }

        $groupMessage = GroupMessage::create([
            'group_id'          => $group->id,
            'reply_to_id'       => $replyToId,
            'sender_identifier' => $userId,
            'body'              => trim($body),
        ]);
        $groupMessage->load('replyTo');

        if (Schema::hasColumn('group_members', 'last_read_message_id')) {
            GroupMember::where('group_id', $group->id)
                ->where('user_identifier', $userId)
                ->update(['last_read_message_id' => $groupMessage->id]);
        }

        $directory = $this->portalService->getDirectoryContacts();
        $formattedMessage = $this->formatPesanGrup($groupMessage, $directory, $currentUser);

        try {
            broadcast(new MessageSent($formattedMessage, $group->id))->toOthers();
        } catch (\Throwable $e) {
            // Jangan gagalkan penyimpanan pesan jika server Reverb sedang mati
        }

        return $formattedMessage;
    }

    /**
     * Simpan pesan pribadi (1-on-1) baru dan kirim broadcast real-time Reverb.
     *
     * @return array<string, mixed>
     */
    public function sendDirectMessage(string $receiverId, User $currentUser, string $body, ?int $replyToId): array
    {
        $userId = (string) $currentUser->id;
        $receiverProfile = $this->portalService->findUserByIdentifier($receiverId);
        abort_if(! $receiverProfile, 404, 'Kontak tidak ditemukan.');

        $message = Message::create([
            'sender_identifier'   => $userId,
            'receiver_identifier' => $receiverId,
            'reply_to_id'         => $replyToId,
            'body'                => trim($body),
            'is_read'             => false,
        ]);
        $message->load('replyTo');

        $directory = $this->portalService->getDirectoryContacts();
        $formattedMessage = $this->formatPesan($message, $currentUser, $directory);

        $pairIds = [$userId, $receiverId];
        sort($pairIds);
        $personalChannelKey = 'dm_'.implode('_', $pairIds);

        try {
            broadcast(new MessageSent($formattedMessage, $personalChannelKey))->toOthers();
        } catch (\Throwable $e) {
            // Jangan gagalkan penyimpanan pesan jika server Reverb sedang mati
        }

        return $formattedMessage;
    }

    /**
     * Ambil payload pesan grup (mark-read only, pagination pesan lama `before_id`, atau pesan baru `after_id`).
     *
     * @return array<string, mixed>
     */
    public function fetchGroupMessagesPayload(
        Group $group,
        User $currentUser,
        int $afterId,
        int $beforeId,
        bool $markReadOnly
    ): array {
        $userId = (string) $currentUser->id;
        $directory = $this->portalService->getDirectoryContacts();

        $this->markGroupRead($group->id, $userId);

        if ($markReadOnly) {
            return [
                'messages'         => [],
                'has_more'         => false,
                'last_read_id'     => 0,
                'contacts_summary' => $this->directoryService->getContactsSummary($userId)->values(),
            ];
        }

        if ($beforeId > 0) {
            $rawOlder = GroupMessage::query()
                ->with('replyTo')
                ->where('group_id', $group->id)
                ->where('id', '<', $beforeId)
                ->latest('id')
                ->take(51)
                ->get();

            $hasMore = $rawOlder->count() > 50;
            $olderMessages = $rawOlder
                ->take(50)
                ->reverse()
                ->values()
                ->map(fn (GroupMessage $msg): array => $this->formatPesanGrup($msg, $directory, $currentUser))
                ->values();

            return [
                'messages' => $olderMessages,
                'has_more' => $hasMore,
            ];
        }

        $newMessages = GroupMessage::query()
            ->with('replyTo')
            ->where('group_id', $group->id)
            ->where('id', '>', $afterId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (GroupMessage $msg): array => $this->formatPesanGrup($msg, $directory, $currentUser))
            ->values();

        return [
            'messages'         => $newMessages,
            'has_more'         => false,
            'last_read_id'     => 0,
            'contacts_summary' => $this->directoryService->getContactsSummary($userId)->values(),
        ];
    }

    /**
     * Ambil payload pesan pribadi (mark-read only, pagination pesan lama `before_id`, atau pesan baru `after_id`).
     *
     * @return array<string, mixed>
     */
    public function fetchDirectMessagesPayload(
        string $receiverId,
        User $currentUser,
        int $afterId,
        int $beforeId,
        bool $markReadOnly
    ): array {
        $userId = (string) $currentUser->id;

        $this->markDirectMessagesRead($receiverId, $userId);
        $directory = $this->portalService->getDirectoryContacts();

        if ($markReadOnly) {
            return [
                'messages'         => [],
                'has_more'         => false,
                'last_read_id'     => (int) Message::where('sender_identifier', $userId)->where('receiver_identifier', $receiverId)->where('is_read', true)->max('id'),
                'contacts_summary' => $this->directoryService->getContactsSummary($userId)->values(),
            ];
        }

        if ($beforeId > 0) {
            $rawOlder = Message::query()
                ->with('replyTo')
                ->where('id', '<', $beforeId)
                ->where(function (Builder $query) use ($userId, $receiverId): void {
                    $query->where(function (Builder $q) use ($userId, $receiverId): void {
                        $q->where('sender_identifier', $userId)->where('receiver_identifier', $receiverId);
                    })->orWhere(function (Builder $q) use ($userId, $receiverId): void {
                        $q->where('sender_identifier', $receiverId)->where('receiver_identifier', $userId);
                    });
                })
                ->latest('id')
                ->take(51)
                ->get();

            $hasMore = $rawOlder->count() > 50;
            $olderMessages = $rawOlder
                ->take(50)
                ->reverse()
                ->values()
                ->map(fn (Message $msg): array => $this->formatPesan($msg, $currentUser, $directory))
                ->values();

            return [
                'messages' => $olderMessages,
                'has_more' => $hasMore,
            ];
        }

        $newMessages = Message::query()
            ->with('replyTo')
            ->where('id', '>', $afterId)
            ->where(function (Builder $query) use ($userId, $receiverId): void {
                $query->where(function (Builder $q) use ($userId, $receiverId): void {
                    $q->where('sender_identifier', $userId)->where('receiver_identifier', $receiverId);
                })->orWhere(function (Builder $q) use ($userId, $receiverId): void {
                    $q->where('sender_identifier', $receiverId)->where('receiver_identifier', $userId);
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $msg): array => $this->formatPesan($msg, $currentUser, $directory))
            ->values();

        $lastReadId = (int) Message::where('sender_identifier', $userId)
            ->where('receiver_identifier', $receiverId)
            ->where('is_read', true)
            ->max('id');

        return [
            'messages'         => $newMessages,
            'last_read_id'     => $lastReadId,
            'contacts_summary' => $this->directoryService->getContactsSummary($userId)->values(),
        ];
    }

    private function markGroupRead(int $groupId, string $userId): void
    {
        if (Schema::hasColumn('group_members', 'last_read_message_id')) {
            $latestGroupMsgId = (int) GroupMessage::where('group_id', $groupId)->max('id');
            GroupMember::where('group_id', $groupId)
                ->where('user_identifier', $userId)
                ->update(['last_read_message_id' => $latestGroupMsgId]);
        }
    }

    private function markDirectMessagesRead(string $senderId, string $receiverId): void
    {
        Message::where('sender_identifier', $senderId)
            ->where('receiver_identifier', $receiverId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Format data pesan yang dibalas (replyTo) menjadi array ringkas untuk kutipan bubble & WebSocket.
     *
     * @param  array<string, array<string, mixed>>  $directory
     * @return array{id: int, sender_identifier: string, sender_name: string, body: string}|null
     */
    private function formatReplyToPayload(Message|GroupMessage|null $replyMsg, array $directory = [], ?User $currentUser = null): ?array
    {
        if (! $replyMsg) {
            return null;
        }

        $replySenderId = (string) $replyMsg->sender_identifier;
        $replySenderProfile = $this->directoryService->resolveSenderProfile($replySenderId, $directory, $currentUser);
        $replySenderName = (string) ($replySenderProfile['nama_lengkap'] ?? $replySenderProfile['name'] ?? $replySenderId);
        $cleanBody = trim((string) preg_replace('/\s+/u', ' ', (string) $replyMsg->body));

        return [
            'id'                => (int) $replyMsg->id,
            'sender_identifier' => $replySenderId,
            'sender_name'       => $replySenderName,
            'body'              => Str::limit($cleanBody, 140),
        ];
    }

    /**
     * Format model Message personal ke struktur array siap-JSON dengan zona waktu WIB, nama asli pengirim, dan kutipan reply_to.
     *
     * @param  array<string, array<string, mixed>>  $directory
     * @return array<string, mixed>
     */
    private function formatPesan(Message $msg, ?User $currentUser = null, array $directory = []): array
    {
        $tanggal = $msg->created_at->timezone('Asia/Jakarta');
        $hari = $tanggal->isToday()
            ? 'Hari Ini'
            : ($tanggal->isYesterday() ? 'Kemarin' : $tanggal->locale('id')->translatedFormat('l'));

        $senderIdentifier = (string) $msg->sender_identifier;
        $senderName = $this->portalService->getLocalUserName($senderIdentifier)
            ?? ($currentUser && (string) $currentUser->id === $senderIdentifier ? (string) $currentUser->nama_lengkap : $senderIdentifier);

        $replyTo = $msg->relationLoaded('replyTo') ? $msg->replyTo : ($msg->reply_to_id ? $msg->replyTo()->first() : null);

        return [
            'id'                => $msg->id,
            'sender_id'         => $senderIdentifier,
            'sender_identifier' => $senderIdentifier,
            'sender_name'       => $senderName,
            'is_group_message'  => false,
            'body'              => $msg->body,
            'reply_to_id'       => $msg->reply_to_id ? (int) $msg->reply_to_id : null,
            'reply_to'          => $this->formatReplyToPayload($replyTo, $directory, $currentUser),
            'is_read'           => (bool) $msg->is_read,
            'time'              => $tanggal->format('H:i').' WIB',
            'date_key'          => $tanggal->format('Y-m-d'),
            'date_label'        => $hari.', '.$tanggal->locale('id')->translatedFormat('d F Y'),
        ];
    }

    /**
     * Format model GroupMessage ke struktur array siap-JSON lengkap dengan nama pengirim dan kutipan reply_to.
     *
     * @param  array<string, array<string, mixed>>  $directory
     * @return array<string, mixed>
     */
    private function formatPesanGrup(GroupMessage $msg, array $directory, ?User $currentUser = null): array
    {
        $tanggal = $msg->created_at->timezone('Asia/Jakarta');
        $hari = $tanggal->isToday()
            ? 'Hari Ini'
            : ($tanggal->isYesterday() ? 'Kemarin' : $tanggal->locale('id')->translatedFormat('l'));

        $senderProfile = $this->directoryService->resolveSenderProfile((string) $msg->sender_identifier, $directory, $currentUser);
        $senderName = (string) ($senderProfile['nama_lengkap'] ?? $senderProfile['name'] ?? $msg->sender_identifier);
        $replyTo = $msg->relationLoaded('replyTo') ? $msg->replyTo : ($msg->reply_to_id ? $msg->replyTo()->first() : null);

        return [
            'id'                => $msg->id,
            'group_id'          => $msg->group_id,
            'sender_id'         => (string) $msg->sender_identifier,
            'sender_identifier' => (string) $msg->sender_identifier,
            'sender_name'       => $senderName,
            'is_group_message'  => true,
            'body'              => $msg->body,
            'reply_to_id'       => $msg->reply_to_id ? (int) $msg->reply_to_id : null,
            'reply_to'          => $this->formatReplyToPayload($replyTo, $directory, $currentUser),
            'is_read'           => false,
            'time'              => $tanggal->format('H:i').' WIB',
            'date_key'          => $tanggal->format('Y-m-d'),
            'date_label'        => $hari.', '.$tanggal->locale('id')->translatedFormat('d F Y'),
        ];
    }
}
