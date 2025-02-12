<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SslDetail extends Model
{
    // 1. Sesuaikan nama tabel
    protected $table = 'ssl_detailss';

    // 2. Kolom yang bisa diisi massal
    protected $fillable = [
        'website_id',
        'is_valid',
        'valid_from',
        'valid_to',
        'issuer',
        'protocol',
        'certificate_info'
    ];

    // 3. Casting atribut
    protected $casts = [
        'is_valid' => 'boolean',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'certificate_info' => 'array'
    ];

    // 4. Relasi ke Website
    public function website(): BelongsTo
    {
        return $this->belongsTo(
            related: Website::class,
            foreignKey: 'website_id',
            ownerKey: 'id'
        );
    }

    // 5. Accessor untuk valid_from dengan penanganan null
    public function getValidFromAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    // 6. Accessor untuk valid_to dengan penanganan null
    public function getValidToAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    // 7. Cek validitas sertifikat saat ini
    public function isValidNow(): bool
    {
        return $this->is_valid && 
               Carbon::now()->between(
                   $this->valid_from ?? Carbon::minValue(),
                   $this->valid_to ?? Carbon::maxValue()
               );
    }

    // 8. Hitung hari hingga kadaluarsa dengan penanganan null
    public function daysUntilExpiration(): ?int
    {
        if (!$this->valid_to) {
            return null;
        }

        $expirationDate = $this->valid_to instanceof Carbon ? 
            $this->valid_to : 
            Carbon::parse($this->valid_to);

        return Carbon::now()->diffInDays($expirationDate, false);
    }

    // 9. Format issuer tanpa CN=
    public function getFormattedIssuerAttribute(): string
    {
        return preg_replace('/^CN=/i', '', $this->issuer ?? 'Unknown Issuer');
    }

    // 10. Cek protokol aman
    public function isSecureProtocol(): bool
    {
        return in_array(
            strtoupper($this->protocol ?? ''),
            ['TLSV1.2', 'TLSV1.3']
        );
    }

    // 11. Cek sertifikat akan kadaluarsa dalam X hari
    public function isExpiringSoon(int $days = 30): bool
    {
        $daysLeft = $this->daysUntilExpiration();
        return $daysLeft !== null && $daysLeft <= $days;
    }
}