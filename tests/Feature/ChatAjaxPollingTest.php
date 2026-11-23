<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Message;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatAjaxPollingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Buat pasangan pengguna sivitas akademika untuk pengujian percakapan.
     *
     * @return array{mahasiswa: User, dosen: User, kontakLain: User}
     */
    private function buatPenggunaUji(): array
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $roleMahasiswa = Role::firstOrCreate(
            ['nama_role' => 'Mahasiswa'],
            ['deskripsi' => 'Mahasiswa aktif']
        );

        $roleDosen = Role::firstOrCreate(
            ['nama_role' => 'Dosen'],
            ['deskripsi' => 'Dosen pengampu']
        );

        $fakultas = Fakultas::firstOrCreate(
            ['kode_fakultas' => 'FT'],
            ['nama_fakultas' => 'Fakultas Teknik']
        );

        $prodi = ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI'],
            [
                'fakultas_id' => $fakultas->id,
                'nama_prodi' => 'Teknik Informatika',
            ]
        );

        $mahasiswa = User::create([
            'role_id' => $roleMahasiswa->id,
            'identifier' => '2421400177',
            'name' => 'Mochammad Farhan',
            'nama_lengkap' => 'Mochammad Farhan',
            'username' => '2421400177',
            'role_name' => 'mahasiswa',
            'unit' => 'Fakultas Teknik',
            'email_kampus' => '2421400177@mahasiswa.kampus.ac.id',
            'password' => Hash::make('password'),
            'status_akun' => 'Aktif',
            'nim' => '2421400177',
            'tahun_angkatan' => 2024,
            'prodi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
        ]);

        $dosen = User::create([
            'role_id' => $roleDosen->id,
            'identifier' => '0701018501',
            'name' => 'Dr. Ahmad Fauzi',
            'nama_lengkap' => 'Dr. Ahmad Fauzi',
            'username' => '0701018501',
            'role_name' => 'dosen',
            'unit' => 'Fakultas Teknik',
            'email_kampus' => 'ahmad.fauzi@kampus.ac.id',
            'password' => Hash::make('password'),
            'status_akun' => 'Aktif',
            'nidn_nip' => '0701018501',
            'jabatan_akademik' => 'Lektor',
            'prodi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
        ]);

        $kontakLain = User::create([
            'role_id' => $roleMahasiswa->id,
            'identifier' => '2421400178',
            'name' => 'Siti Aminah',
            'nama_lengkap' => 'Siti Aminah',
            'username' => '2421400178',
            'role_name' => 'mahasiswa',
            'unit' => 'Fakultas Teknik',
            'email_kampus' => '2421400178@mahasiswa.kampus.ac.id',
            'password' => Hash::make('password'),
            'status_akun' => 'Aktif',
            'nim' => '2421400178',
            'tahun_angkatan' => 2024,
            'prodi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
        ]);

        return compact('mahasiswa', 'dosen', 'kontakLain');
    }

    /**
     * Siapkan payload sesi SSO untuk pengguna uji agar kompatibel dengan middleware EnsureSsoAuthenticated.
     *
     * @return array<string, mixed>
     */
    private function ssoSessionFor(User $user): array
    {
        $user->loadMissing(['role', 'fakultas', 'prodi']);
        $identifier = (string) ($user->identifier ?: $user->username);
        $roleName = $user->roleName();

        return [
            'sso_user' => [
                'identifier' => $identifier,
                'username' => (string) $user->username,
                'nim' => $user->nim,
                'nidn_nip' => $user->nidn_nip,
                'name' => (string) $user->nama_lengkap,
                'nama_lengkap' => (string) $user->nama_lengkap,
                'role' => $roleName,
                'prodi' => $user->prodi?->nama_prodi ?? 'Teknik Informatika',
                'fakultas' => $user->fakultas?->nama_fakultas ?? 'Fakultas Teknik',
                'unit' => (string) ($user->unit ?: 'Fakultas Teknik'),
                'tahun_angkatan' => $user->tahun_angkatan,
                'email_kampus' => $user->email_kampus,
                'status_akun' => (string) ($user->status_akun ?: 'Aktif'),
            ],
            'krs_synced' => true,
        ];
    }

    public function test_user_can_send_message_via_ajax_and_receive_json_payload(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen] = $this->buatPenggunaUji();

        $response = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->postJson(route('chat.send', $dosen->identifier), [
                'body' => '  Assalamualaikum Pak, izin konsultasi Bab 4.  ',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message' => [
                    'id',
                    'sender_id',
                    'body',
                    'is_read',
                    'time',
                    'date_key',
                    'date_label',
                ],
            ])
            ->assertJsonPath('message.sender_id', $mahasiswa->identifier)
            ->assertJsonPath('message.body', 'Assalamualaikum Pak, izin konsultasi Bab 4.')
            ->assertJsonPath('message.is_read', false)
            ->assertJsonPath('contacts_summary.0.contact_id', $dosen->identifier)
            ->assertJsonPath('contacts_summary.0.last_message.id', $response->json('message.id'))
            ->assertJsonPath('contacts_summary.0.last_message.is_sender_me', true)
            ->assertJsonPath('contacts_summary.0.last_message.is_read', false);

        $this->assertDatabaseHas('messages', [
            'sender_identifier' => $mahasiswa->identifier,
            'receiver_identifier' => $dosen->identifier,
            'body' => 'Assalamualaikum Pak, izin konsultasi Bab 4.',
            'is_read' => false,
        ]);
    }

    public function test_non_ajax_form_post_still_redirects_to_chat_show(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen] = $this->buatPenggunaUji();

        $response = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->post(route('chat.send', $dosen->identifier), [
                'body' => 'Pesan sinkron biasa',
            ]);

        $response->assertRedirect(route('chat.show', $dosen->identifier));
    }

    public function test_polling_endpoint_returns_only_new_conversation_messages_and_updates_read_status(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        $pesanLama = Message::create([
            'sender_identifier' => $mahasiswa->identifier,
            'receiver_identifier' => $dosen->identifier,
            'body' => 'Pesan awal',
            'is_read' => true,
        ]);

        $pesanBaruDosen = Message::create([
            'sender_identifier' => $dosen->identifier,
            'receiver_identifier' => $mahasiswa->identifier,
            'body' => 'Silakan kirim draf terbaru ya.',
            'is_read' => false,
        ]);

        // Pesan percakapan lain tidak boleh ikut bocor pada query OR
        Message::create([
            'sender_identifier' => $dosen->identifier,
            'receiver_identifier' => $kontakLain->identifier,
            'body' => 'Pesan rahasia untuk mahasiswa lain.',
            'is_read' => false,
        ]);

        $pollMahasiswa = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->getJson(
                route('chat.messages', ['receiver_id' => $dosen->identifier, 'after_id' => $pesanLama->id])
            );

        $pollMahasiswa->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $pesanBaruDosen->id)
            ->assertJsonPath('messages.0.body', 'Silakan kirim draf terbaru ya.')
            ->assertJsonPath('last_read_id', $pesanLama->id)
            ->assertJsonCount(1, 'contacts_summary')
            ->assertJsonPath('contacts_summary.0.contact_id', $dosen->identifier)
            ->assertJsonPath('contacts_summary.0.unread_count', 0)
            ->assertJsonPath('contacts_summary.0.last_message.id', $pesanBaruDosen->id)
            ->assertJsonPath('contacts_summary.0.last_message.is_sender_me', false);

        $this->assertTrue((bool) $pesanBaruDosen->fresh()->is_read);

        // Saat dosen melakukan polling berikutnya, last_read_id harus mencerminkan pesan yang baru dibaca mahasiswa
        $pollDosen = $this->actingAs($dosen)
            ->withSession($this->ssoSessionFor($dosen))
            ->getJson(
                route('chat.messages', ['receiver_id' => $mahasiswa->identifier, 'after_id' => $pesanBaruDosen->id])
            );

        $pollDosen->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertJsonPath('last_read_id', $pesanBaruDosen->id);
    }

    public function test_user_cannot_send_ajax_message_to_self(): void
    {
        ['mahasiswa' => $mahasiswa] = $this->buatPenggunaUji();

        $response = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->postJson(route('chat.send', $mahasiswa->identifier), [
                'body' => 'Halo diri sendiri',
            ]);

        $response->assertStatus(422);
    }

    public function test_dashboard_orders_contacts_by_latest_message_with_unread_count_and_preview(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        Message::create([
            'sender_identifier' => $mahasiswa->identifier,
            'receiver_identifier' => $dosen->identifier,
            'body' => 'Pak, draf Bab 3 sudah saya kirim.',
            'is_read' => true,
        ]);

        Message::create([
            'sender_identifier' => $kontakLain->identifier,
            'receiver_identifier' => $mahasiswa->identifier,
            'body' => 'Farhan, jadi kerja kelompok?',
            'is_read' => false,
        ]);

        $pesanTerbaru = Message::create([
            'sender_identifier' => $kontakLain->identifier,
            'receiver_identifier' => $mahasiswa->identifier,
            'body' => "Kabari ya,\nsebelum jam 4.",
            'is_read' => false,
        ]);

        $response = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-contact-id="'.$kontakLain->identifier.'"',
            'data-contact-id="'.$dosen->identifier.'"',
            'data-contact-id="unit_baak"',
        ], false);
        $response->assertSee('data-last-message-id="'.$pesanTerbaru->id.'"', false);
        $response->assertSee('Kabari ya, sebelum jam 4.');
        $response->assertSee('data-summary-url="'.route('chat.contacts').'"', false);

        $response->assertViewHas('kontak', function ($kontak) use ($kontakLain, $dosen, $pesanTerbaru): bool {
            $pertama = $kontak->first();
            $kedua = $kontak->get(1);
            $ketiga = $kontak->get(2);

            return $pertama->id === $kontakLain->identifier
                && $pertama->unread_count === 2
                && $pertama->last_message['id'] === $pesanTerbaru->id
                && $pertama->last_message['is_sender_me'] === false
                && $kedua->id === $dosen->identifier
                && $kedua->unread_count === 0
                && $kedua->last_message['is_sender_me'] === true
                && $kedua->last_message['is_read'] === true
                && $ketiga !== null
                && $ketiga->last_message === null;
        });
    }

    public function test_contacts_summary_endpoint_returns_sorted_unread_counts_and_last_messages(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        $pesanKeDosen = Message::create([
            'sender_identifier' => $mahasiswa->identifier,
            'receiver_identifier' => $dosen->identifier,
            'body' => 'Selamat pagi Pak.',
            'is_read' => false,
        ]);

        Message::create([
            'sender_identifier' => $kontakLain->identifier,
            'receiver_identifier' => $mahasiswa->identifier,
            'body' => 'Pesan pertama dari Siti.',
            'is_read' => false,
        ]);

        $pesanTerbaruSiti = Message::create([
            'sender_identifier' => $kontakLain->identifier,
            'receiver_identifier' => $mahasiswa->identifier,
            'body' => 'Pesan kedua dari Siti.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->getJson(route('chat.contacts'));

        $response->assertOk()
            ->assertJsonCount(2, 'contacts_summary')
            ->assertJsonPath('contacts_summary.0.contact_id', $kontakLain->identifier)
            ->assertJsonPath('contacts_summary.0.unread_count', 2)
            ->assertJsonPath('contacts_summary.0.last_message.id', $pesanTerbaruSiti->id)
            ->assertJsonPath('contacts_summary.0.last_message.body', 'Pesan kedua dari Siti.')
            ->assertJsonPath('contacts_summary.0.last_message.is_sender_me', false)
            ->assertJsonPath('contacts_summary.1.contact_id', $dosen->identifier)
            ->assertJsonPath('contacts_summary.1.unread_count', 0)
            ->assertJsonPath('contacts_summary.1.last_message.id', $pesanKeDosen->id)
            ->assertJsonPath('contacts_summary.1.last_message.is_sender_me', true)
            ->assertJsonStructure([
                'contacts_summary' => [
                    '*' => [
                        'contact_id',
                        'unread_count',
                        'last_message' => ['id', 'body', 'time', 'timestamp', 'is_sender_me', 'is_read'],
                    ],
                ],
            ]);
    }

    public function test_guest_cannot_access_contacts_summary_endpoint(): void
    {
        $this->getJson(route('chat.contacts'))->assertUnauthorized();
    }

    public function test_chat_show_renders_polling_configuration_attributes(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        // Pesan belum dibaca dari kontak lain harus muncul sebagai badge unread di sidebar
        Message::create([
            'sender_identifier' => $kontakLain->identifier,
            'receiver_identifier' => $mahasiswa->identifier,
            'body' => 'Halo Farhan, ada tugas kelompok.',
            'is_read' => false,
        ]);

        // Pesan dalam percakapan aktif dirender lewat komponen x-message-bubble
        $pesan = Message::create([
            'sender_identifier' => $mahasiswa->identifier,
            'receiver_identifier' => $dosen->identifier,
            'body' => 'Selamat siang Pak Ahmad.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($mahasiswa)
            ->withSession($this->ssoSessionFor($mahasiswa))
            ->get(route('chat.show', $dosen->identifier));

        $response->assertOk();
        $response->assertSee('data-fetch-url="'.route('chat.messages', $dosen->identifier).'"', false);
        $response->assertSee('id="chat-messages-container"', false);
        $response->assertSee('id="chat-sidebar"', false);
        $response->assertSee('data-sidebar-open', false);
        $response->assertSee('data-message-id="'.$pesan->id.'"', false);
        $response->assertSee(asset('js/chat.js'), false);
        $response->assertSee('AF'); // Inisial "Dr. Ahmad Fauzi" tanpa gelar "Dr."
    }

    public function test_user_presentation_helpers_format_academic_attributes_cleanly(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen] = $this->buatPenggunaUji();

        $mahasiswa->load(['role', 'fakultas', 'prodi']);
        $dosen->load(['role', 'fakultas', 'prodi']);

        $this->assertSame('MF', $mahasiswa->initials());
        $this->assertSame('NIM 2421400177', $mahasiswa->identityBadge());
        $this->assertSame('Fakultas Teknik', $mahasiswa->affiliationLabel());

        $this->assertSame('AF', $dosen->initials());
        $this->assertSame('NIDN 0701018501', $dosen->identityBadge());
        $this->assertSame('Lektor • Teknik Informatika', $dosen->contactSubtitle());
    }
}
