<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Message;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $roleMahasiswa = Role::create([
            'nama_role' => 'Mahasiswa',
            'deskripsi' => 'Mahasiswa aktif',
        ]);

        $roleDosen = Role::create([
            'nama_role' => 'Dosen',
            'deskripsi' => 'Dosen pengampu',
        ]);

        $fakultas = Fakultas::create([
            'kode_fakultas' => 'FT',
            'nama_fakultas' => 'Fakultas Teknik',
        ]);

        $prodi = ProgramStudi::create([
            'fakultas_id' => $fakultas->id,
            'kode_prodi' => 'TI',
            'nama_prodi' => 'Teknik Informatika',
        ]);

        $mahasiswa = User::create([
            'role_id' => $roleMahasiswa->id,
            'nama_lengkap' => 'Mochammad Farhan',
            'username' => '2421400177',
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
            'nama_lengkap' => 'Dr. Ahmad Fauzi',
            'username' => '198501012010',
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
            'nama_lengkap' => 'Siti Aminah',
            'username' => '2421400178',
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

    public function test_user_can_send_message_via_ajax_and_receive_json_payload(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen] = $this->buatPenggunaUji();

        $response = $this->actingAs($mahasiswa)->postJson(route('chat.send', $dosen->id), [
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
            ->assertJsonPath('message.sender_id', $mahasiswa->id)
            ->assertJsonPath('message.body', 'Assalamualaikum Pak, izin konsultasi Bab 4.')
            ->assertJsonPath('message.is_read', false)
            ->assertJsonPath('contacts_summary.0.contact_id', $dosen->id)
            ->assertJsonPath('contacts_summary.0.last_message.id', $response->json('message.id'))
            ->assertJsonPath('contacts_summary.0.last_message.is_sender_me', true)
            ->assertJsonPath('contacts_summary.0.last_message.is_read', false);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $mahasiswa->id,
            'receiver_id' => $dosen->id,
            'body' => 'Assalamualaikum Pak, izin konsultasi Bab 4.',
            'is_read' => false,
        ]);
    }

    public function test_non_ajax_form_post_still_redirects_to_chat_show(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen] = $this->buatPenggunaUji();

        $response = $this->actingAs($mahasiswa)->post(route('chat.send', $dosen->id), [
            'body' => 'Pesan sinkron biasa',
        ]);

        $response->assertRedirect(route('chat.show', $dosen->id));
    }

    public function test_polling_endpoint_returns_only_new_conversation_messages_and_updates_read_status(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        $pesanLama = Message::create([
            'sender_id' => $mahasiswa->id,
            'receiver_id' => $dosen->id,
            'body' => 'Pesan awal',
            'is_read' => true,
        ]);

        $pesanBaruDosen = Message::create([
            'sender_id' => $dosen->id,
            'receiver_id' => $mahasiswa->id,
            'body' => 'Silakan kirim draf terbaru ya.',
            'is_read' => false,
        ]);

        // Pesan percakapan lain tidak boleh ikut bocor pada query OR
        Message::create([
            'sender_id' => $dosen->id,
            'receiver_id' => $kontakLain->id,
            'body' => 'Pesan rahasia untuk mahasiswa lain.',
            'is_read' => false,
        ]);

        $pollMahasiswa = $this->actingAs($mahasiswa)->getJson(
            route('chat.messages', ['receiver_id' => $dosen->id, 'after_id' => $pesanLama->id])
        );

        $pollMahasiswa->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $pesanBaruDosen->id)
            ->assertJsonPath('messages.0.body', 'Silakan kirim draf terbaru ya.')
            ->assertJsonPath('last_read_id', $pesanLama->id)
            ->assertJsonCount(1, 'contacts_summary')
            ->assertJsonPath('contacts_summary.0.contact_id', $dosen->id)
            ->assertJsonPath('contacts_summary.0.unread_count', 0)
            ->assertJsonPath('contacts_summary.0.last_message.id', $pesanBaruDosen->id)
            ->assertJsonPath('contacts_summary.0.last_message.is_sender_me', false);

        $this->assertTrue($pesanBaruDosen->fresh()->is_read);

        // Saat dosen melakukan polling berikutnya, last_read_id harus mencerminkan pesan yang baru dibaca mahasiswa
        $pollDosen = $this->actingAs($dosen)->getJson(
            route('chat.messages', ['receiver_id' => $mahasiswa->id, 'after_id' => $pesanBaruDosen->id])
        );

        $pollDosen->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertJsonPath('last_read_id', $pesanBaruDosen->id);
    }

    public function test_user_cannot_send_ajax_message_to_self(): void
    {
        ['mahasiswa' => $mahasiswa] = $this->buatPenggunaUji();

        $response = $this->actingAs($mahasiswa)->postJson(route('chat.send', $mahasiswa->id), [
            'body' => 'Halo diri sendiri',
        ]);

        $response->assertStatus(422);
    }

    public function test_dashboard_orders_contacts_by_latest_message_with_unread_count_and_preview(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        // Kontak tanpa percakapan yang secara alfabetis berada paling atas ("Bagus" < "Dr." < "Siti")
        $kontakTanpaChat = User::create([
            'role_id' => $mahasiswa->role_id,
            'nama_lengkap' => 'Bagus Pratama',
            'username' => '2421400179',
            'email_kampus' => '2421400179@mahasiswa.kampus.ac.id',
            'password' => Hash::make('password'),
            'status_akun' => 'Aktif',
            'nim' => '2421400179',
        ]);

        Message::create([
            'sender_id' => $mahasiswa->id,
            'receiver_id' => $dosen->id,
            'body' => 'Pak, draf Bab 3 sudah saya kirim.',
            'is_read' => true,
        ]);

        Message::create([
            'sender_id' => $kontakLain->id,
            'receiver_id' => $mahasiswa->id,
            'body' => 'Farhan, jadi kerja kelompok?',
            'is_read' => false,
        ]);

        $pesanTerbaru = Message::create([
            'sender_id' => $kontakLain->id,
            'receiver_id' => $mahasiswa->id,
            'body' => "Kabari ya,\nsebelum jam 4.",
            'is_read' => false,
        ]);

        $response = $this->actingAs($mahasiswa)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-contact-id="'.$kontakLain->id.'"',
            'data-contact-id="'.$dosen->id.'"',
            'data-contact-id="'.$kontakTanpaChat->id.'"',
        ], false);
        $response->assertSee('data-last-message-id="'.$pesanTerbaru->id.'"', false);
        $response->assertSee('Kabari ya, sebelum jam 4.');
        $response->assertSee('data-summary-url="'.route('chat.contacts').'"', false);

        $response->assertViewHas('kontak', function ($kontak) use ($kontakLain, $dosen, $kontakTanpaChat, $pesanTerbaru): bool {
            $pertama = $kontak->first();
            $kedua = $kontak->get(1);
            $ketiga = $kontak->get(2);

            return $pertama->id === $kontakLain->id
                && $pertama->unread_count === 2
                && $pertama->last_message['id'] === $pesanTerbaru->id
                && $pertama->last_message['is_sender_me'] === false
                && $kedua->id === $dosen->id
                && $kedua->unread_count === 0
                && $kedua->last_message['is_sender_me'] === true
                && $kedua->last_message['is_read'] === true
                && $ketiga->id === $kontakTanpaChat->id
                && $ketiga->last_message === null;
        });
    }

    public function test_contacts_summary_endpoint_returns_sorted_unread_counts_and_last_messages(): void
    {
        ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontakLain' => $kontakLain] = $this->buatPenggunaUji();

        $pesanKeDosen = Message::create([
            'sender_id' => $mahasiswa->id,
            'receiver_id' => $dosen->id,
            'body' => 'Selamat pagi Pak.',
            'is_read' => false,
        ]);

        Message::create([
            'sender_id' => $kontakLain->id,
            'receiver_id' => $mahasiswa->id,
            'body' => 'Pesan pertama dari Siti.',
            'is_read' => false,
        ]);

        $pesanTerbaruSiti = Message::create([
            'sender_id' => $kontakLain->id,
            'receiver_id' => $mahasiswa->id,
            'body' => 'Pesan kedua dari Siti.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($mahasiswa)->getJson(route('chat.contacts'));

        $response->assertOk()
            ->assertJsonCount(2, 'contacts_summary')
            ->assertJsonPath('contacts_summary.0.contact_id', $kontakLain->id)
            ->assertJsonPath('contacts_summary.0.unread_count', 2)
            ->assertJsonPath('contacts_summary.0.last_message.id', $pesanTerbaruSiti->id)
            ->assertJsonPath('contacts_summary.0.last_message.body', 'Pesan kedua dari Siti.')
            ->assertJsonPath('contacts_summary.0.last_message.is_sender_me', false)
            ->assertJsonPath('contacts_summary.1.contact_id', $dosen->id)
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
            'sender_id' => $kontakLain->id,
            'receiver_id' => $mahasiswa->id,
            'body' => 'Halo Farhan, ada tugas kelompok.',
            'is_read' => false,
        ]);

        // Pesan dalam percakapan aktif dirender lewat komponen x-message-bubble
        $pesan = Message::create([
            'sender_id' => $mahasiswa->id,
            'receiver_id' => $dosen->id,
            'body' => 'Selamat siang Pak Ahmad.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($mahasiswa)->get(route('chat.show', $dosen->id));

        $response->assertOk();
        $response->assertSee('data-fetch-url="'.route('chat.messages', $dosen->id).'"', false);
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

