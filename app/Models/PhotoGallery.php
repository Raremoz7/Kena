<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $status
 * @property string|null $description
 * @property string $access_code
 * @property array<int, array{min_qty: int, unit_price_cents: int}> $price_tiers
 * @property-read Event $event
 */
class PhotoGallery extends Model
{
    protected $guarded = [];

    /** Palavras do ballet: fáceis de ditar e de ler em voz alta. */
    private const CODE_WORDS = [
        'NOZES', 'TUTU', 'PALCO', 'BALLET', 'CORTINA', 'SAPATILHA', 'ESTRELA', 'PIRUETA',
        'ARABESCO', 'PLIE', 'BAILARINA', 'CISNE', 'FLOCO', 'LAREIRA', 'PRESENTE', 'ENSAIO',
    ];

    protected static function booted(): void
    {
        static::creating(function (PhotoGallery $gallery): void {
            $gallery->access_code ??= self::newAccessCode();
        });
    }

    /** Código curto e único, no formato PALAVRA-1234. */
    public static function newAccessCode(): string
    {
        do {
            $code = self::CODE_WORDS[array_rand(self::CODE_WORDS)].'-'.random_int(1000, 9999);
        } while (self::where('access_code', $code)->exists());

        return $code;
    }

    /** Ignora caixa, espaço e hífen: "nozes 4821" vale como NOZES-4821. */
    public static function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function acceptsCode(string $code): bool
    {
        $given = self::normalizeCode($code);

        return $given !== '' && hash_equals(self::normalizeCode($this->access_code), $given);
    }

    /** @return BelongsToMany<User, $this> */
    public function unlockedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'photo_gallery_user')->withPivot('created_at');
    }

    protected function casts(): array
    {
        return [
            'price_tiers' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position');
    }

    /** @return HasMany<PhotoGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(PhotoGroup::class)->orderBy('position');
    }

    /** @return HasMany<PhotoPerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(PhotoPerson::class)->orderBy('name');
    }

    /** Faixas em ordem crescente de quantidade. */
    public function sortedTiers(): array
    {
        $tiers = $this->price_tiers;
        usort($tiers, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);

        return $tiers;
    }
}
