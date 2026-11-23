<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'tahun_angkatan' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }

    public function unitKampus(): HasOne
    {
        return $this->hasOne(UnitKampus::class, 'user_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Ambil maksimal 2 huruf inisial nama lengkap tanpa gelar akademik.
     */
    public function initials(): string
    {
        $fullName = (string) ($this->nama_lengkap ?: $this->name ?: $this->identifier ?: $this->username);
        $cleanName = trim((string) preg_replace('/\b(Prof|Dr|Ir|H|Hj)\.\s*/i', '', $fullName));
        $words = preg_split('/\s+/', $cleanName) ?: [];

        $initials = collect($words)
            ->filter()
            ->map(fn (string $word): string => strtoupper(substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return $initials !== '' ? $initials : 'SK';
    }

    /**
     * Ambil nama role mentah pengguna (contoh: Mahasiswa, Dosen, Staff_Unit).
     */
    public function roleName(): string
    {
        if ($this->relationLoaded('role') && $this->getRelation('role') !== null) {
            return (string) ($this->getRelation('role')->nama_role ?? 'Mahasiswa');
        }

        if (! empty($this->role_name)) {
            return match (strtolower((string) $this->role_name)) {
                'dosen', 'admin_grup' => 'Dosen',
                'staff_unit', 'unit kerja' => 'Staff_Unit',
                'admin' => 'Admin',
                default => 'Mahasiswa',
            };
        }

        return (string) ($this->role?->nama_role ?? 'Mahasiswa');
    }

    /**
     * Ambil label role yang ramah baca tanpa garis bawah.
     */
    public function roleLabel(): string
    {
        return str_replace('_', ' ', $this->roleName());
    }

    /**
     * Ambil nomor identitas utama pengguna (NIM, NIDN/NIP, Kode Unit, atau username).
     */
    public function identityNumber(): string
    {
        return (string) ($this->nim ?? $this->nidn_nip ?? $this->identifier ?? $this->unitKampus?->kode_unit ?? $this->username);
    }

    /**
     * Ambil label identitas lengkap dengan prefiks jenis nomor induk.
     */
    public function identityBadge(): string
    {
        if ($this->nim) {
            return 'NIM '.$this->nim;
        }

        if ($this->nidn_nip) {
            return 'NIDN '.$this->nidn_nip;
        }

        if ($this->unitKampus?->kode_unit) {
            return 'Unit '.$this->unitKampus->kode_unit;
        }

        return (string) ($this->identifier ?: $this->username);
    }

    /**
     * Ambil label afiliasi utama (Fakultas, Unit Kerja, Prodi, atau Role).
     */
    public function affiliationLabel(): string
    {
        return (string) ($this->fakultas?->nama_fakultas
            ?? $this->unitKampus?->nama_unit_kerja
            ?? $this->prodi?->nama_prodi
            ?? $this->unit
            ?? $this->roleLabel());
    }

    /**
     * Ambil deskripsi singkat satu baris untuk daftar kontak sivitas akademika.
     */
    public function contactSubtitle(): string
    {
        $role = $this->roleName();

        if ($role === 'Staff_Unit') {
            return (string) ($this->unitKampus?->deskripsi_layanan
                ?? $this->unitKampus?->nama_unit_kerja
                ?? 'Layanan Resmi Unit Kampus');
        }

        if ($role === 'Dosen') {
            if ($this->jabatan_akademik) {
                return $this->jabatan_akademik.($this->prodi ? ' • '.$this->prodi->nama_prodi : '');
            }

            return (string) ($this->prodi?->nama_prodi ?? $this->email_kampus);
        }

        if ($role === 'Mahasiswa') {
            if ($this->prodi) {
                return $this->prodi->nama_prodi.($this->tahun_angkatan ? ' • '.$this->tahun_angkatan : '');
            }

            return (string) $this->email_kampus;
        }

        return (string) ($this->role?->deskripsi ?? $this->email_kampus);
    }
}
