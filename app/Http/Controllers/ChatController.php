<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\TahunAkademik;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatController extends Controller
{
    /**
     * Tampilkan halaman dashboard komunikasi beserta daftar kontak sivitas akademika.
     */
    public function index(): View
    {
        $kontak = $this->ambilKontak();
        $tahunAktif = $this->ambilTahunAktif();

        return view('dashboard', compact('kontak', 'tahunAktif'));
    }

    /**
     * Tampilkan riwayat obrolan antara pengguna yang login dan kontak terpilih.
     */
    public function show(int $receiver_id): View|RedirectResponse
    {
        if ($receiver_id === Auth::id()) {
            return redirect()->route('dashboard');
        }

        $receiver = User::with(['role', 'prodi', 'fakultas', 'unitKampus'])->findOrFail($receiver_id);
        $userId = (int) Auth::id();

        $messages = Message::with('sender')
            ->where(function (Builder $query) use ($userId, $receiver_id): void {
                $query->where(function (Builder $q) use ($userId, $receiver_id): void {
                    $q->where('sender_id', $userId)->where('receiver_id', $receiver_id);
                })->orWhere(function (Builder $q) use ($userId, $receiver_id): void {
                    $q->where('sender_id', $receiver_id)->where('receiver_id', $userId);
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Tandai pesan masuk dari kontak ini sebagai sudah dibaca.
        Message::where('sender_id', $receiver_id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $kontak = $this->ambilKontak();
        $tahunAktif = $this->ambilTahunAktif();

        return view('dashboard', compact('kontak', 'messages', 'receiver', 'tahunAktif'));
    }

    /**
     * Simpan pesan baru ke tabel messages lalu kembalikan JSON (AJAX) atau redirect.
     */
    public function sendMessage(Request $request, int $receiver_id): RedirectResponse|JsonResponse
    {
        if ($receiver_id === Auth::id()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tidak dapat mengirim pesan ke diri sendiri.',
                ], 422);
            }

            return redirect()->route('dashboard');
        }

        $receiver = User::findOrFail($receiver_id);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'Pesan tidak boleh kosong.',
            'body.max' => 'Pesan maksimal 5000 karakter.',
        ]);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $receiver->id,
            'body' => trim($validated['body']),
            'is_read' => false,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->formatPesan($message),
                'contacts_summary' => $this->ambilRingkasanKontak((int) Auth::id())->values(),
            ], 201);
        }

        return redirect()->route('chat.show', $receiver->id);
    }

    /**
     * Ambil pesan baru setelah ID terakhir, status pesan terbaca, dan ringkasan sidebar untuk AJAX polling.
     */
    public function fetchMessages(Request $request, int $receiver_id): JsonResponse
    {
        $userId = (int) Auth::id();

        if ($receiver_id === $userId) {
            return response()->json([
                'messages' => [],
                'last_read_id' => 0,
                'contacts_summary' => $this->ambilRingkasanKontak($userId)->values(),
            ]);
        }

        User::findOrFail($receiver_id);
        $afterId = max(0, (int) $request->query('after_id', 0));

        Message::where('sender_id', $receiver_id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $newMessages = Message::where('id', '>', $afterId)
            ->where(function (Builder $query) use ($userId, $receiver_id): void {
                $query->where(function (Builder $q) use ($userId, $receiver_id): void {
                    $q->where('sender_id', $userId)->where('receiver_id', $receiver_id);
                })->orWhere(function (Builder $q) use ($userId, $receiver_id): void {
                    $q->where('sender_id', $receiver_id)->where('receiver_id', $userId);
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $msg): array => $this->formatPesan($msg))
            ->values();

        $lastReadId = (int) Message::where('sender_id', $userId)
            ->where('receiver_id', $receiver_id)
            ->where('is_read', true)
            ->max('id');

        return response()->json([
            'messages' => $newMessages,
            'last_read_id' => $lastReadId,
            'contacts_summary' => $this->ambilRingkasanKontak($userId)->values(),
        ]);
    }

    /**
     * Ambil ringkasan sidebar (unread count & pesan terakhir) untuk polling di halaman dashboard tanpa obrolan aktif.
     */
    public function contactsSummary(): JsonResponse
    {
        return response()->json([
            'contacts_summary' => $this->ambilRingkasanKontak((int) Auth::id())->values(),
        ]);
    }

    /**
     * Format model Message ke struktur array siap-JSON dengan zona waktu WIB.
     *
     * @return array{id: int, sender_id: int, body: string, is_read: bool, time: string, date_key: string, date_label: string}
     */
    private function formatPesan(Message $msg): array
    {
        $tanggal = $msg->created_at->timezone('Asia/Jakarta');
        $hari = $tanggal->isToday()
            ? 'Hari Ini'
            : ($tanggal->isYesterday() ? 'Kemarin' : $tanggal->locale('id')->translatedFormat('l'));

        return [
            'id' => $msg->id,
            'sender_id' => $msg->sender_id,
            'body' => $msg->body,
            'is_read' => (bool) $msg->is_read,
            'time' => $tanggal->format('H:i').' WIB',
            'date_key' => $tanggal->format('Y-m-d'),
            'date_label' => $hari.', '.$tanggal->locale('id')->translatedFormat('d F Y'),
        ];
    }

    /**
     * Bangun ringkasan percakapan per kontak (pesan terakhir & jumlah belum dibaca), diurutkan dari yang terbaru.
     *
     * @return SupportCollection<int, array{contact_id: int, unread_count: int, last_message: array{id: int, body: string, time: string, timestamp: string, is_sender_me: bool, is_read: bool}}>
     */
    private function ambilRingkasanKontak(int $userId): SupportCollection
    {
        $lastMessageIds = Message::query()
            ->selectRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS contact_id', [$userId])
            ->selectRaw('MAX(id) AS last_message_id')
            ->where(function (Builder $query) use ($userId): void {
                $query->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->groupBy('contact_id')
            ->pluck('last_message_id', 'contact_id');

        if ($lastMessageIds->isEmpty()) {
            return collect();
        }

        $lastMessages = Message::whereIn('id', $lastMessageIds->values())->get()->keyBy('id');

        $unreadCounts = Message::query()
            ->selectRaw('sender_id, COUNT(*) AS total')
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        return $lastMessageIds
            ->mapWithKeys(function (int|string $lastMessageId, int|string $contactId) use ($lastMessages, $unreadCounts, $userId): array {
                $message = $lastMessages->get((int) $lastMessageId);

                if ($message === null) {
                    return [];
                }

                return [
                    (int) $contactId => [
                        'contact_id' => (int) $contactId,
                        'unread_count' => (int) ($unreadCounts[$contactId] ?? 0),
                        'last_message' => $this->formatPesanTerakhir($message, $userId),
                    ],
                ];
            })
            ->sortByDesc(fn (array $ringkasan): int => $ringkasan['last_message']['id']);
    }

    /**
     * Format pesan terakhir untuk cuplikan satu baris di sidebar.
     *
     * @return array{id: int, body: string, time: string, timestamp: string, is_sender_me: bool, is_read: bool}
     */
    private function formatPesanTerakhir(Message $msg, int $userId): array
    {
        $tanggal = $msg->created_at->timezone('Asia/Jakarta');

        return [
            'id' => $msg->id,
            'body' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $msg->body)), 120),
            'time' => $this->formatWaktuSidebar($tanggal),
            'timestamp' => $tanggal->toIso8601String(),
            'is_sender_me' => (int) $msg->sender_id === $userId,
            'is_read' => (bool) $msg->is_read,
        ];
    }

    /**
     * Format waktu ringkas ala aplikasi chat: jam (hari ini), "Kemarin", nama hari (≤ 6 hari), atau tanggal.
     */
    private function formatWaktuSidebar(CarbonInterface $tanggal): string
    {
        if ($tanggal->isToday()) {
            return $tanggal->format('H:i');
        }

        if ($tanggal->isYesterday()) {
            return 'Kemarin';
        }

        if ($tanggal->greaterThanOrEqualTo(now($tanggal->getTimezone())->subDays(6)->startOfDay())) {
            return $tanggal->locale('id')->translatedFormat('l');
        }

        return $tanggal->format('d/m/y');
    }

    /**
     * Ambil semua kontak beserta unread_count & last_message, diurutkan dari percakapan terbaru lalu nama.
     */
    private function ambilKontak(): Collection
    {
        $currentUser = Auth::user();
        $currentUser->loadMissing(['role', 'fakultas', 'prodi', 'unitKampus']);

        $ringkasanKontak = $this->ambilRingkasanKontak((int) $currentUser->id);

        return User::with(['role', 'prodi', 'fakultas', 'unitKampus'])
            ->where('id', '!=', $currentUser->id)
            ->orderBy('nama_lengkap')
            ->get()
            ->each(function (User $kontak) use ($ringkasanKontak): void {
                $ringkasan = $ringkasanKontak->get($kontak->id);

                $kontak->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
                $kontak->setAttribute('last_message', $ringkasan['last_message'] ?? null);
            })
            ->sortByDesc(fn (User $kontak): int => $kontak->last_message['id'] ?? 0)
            ->values();
    }

    private function ambilTahunAktif(): ?TahunAkademik
    {
        return TahunAkademik::where('is_aktif', true)->first();
    }
}
