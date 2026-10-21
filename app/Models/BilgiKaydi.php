<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bilgi bankasındaki tek bir kayıt — teklif yazılırken kullanılan kurum
 * bilgisi.
 *
 * ASIL VERİ BURADA. Chroma yalnızca bu satırlardan türetilen vektör
 * indeksidir; kaybolursa `bilgi:indeksle` ile yeniden kurulur, tersi mümkün
 * değildir.
 */
class BilgiKaydi extends Model
{
    protected $table = 'bilgi_kayitlari';

    protected $fillable = ['baslik', 'kategori', 'metin', 'kaynak', 'chroma_id', 'indekslendi', 'ekleyen_id'];

    protected function casts(): array
    {
        return ['indekslendi' => 'datetime'];
    }

    public function ekleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ekleyen_id');
    }

    /**
     * Kaynağın EKRANDA görünen adı.
     *
     * Alanın kendisi makineye göre yazılıyor ("chroma", "kurulum"). Ekranda
     * ham değer görmek kimseye bir şey anlatmaz; bilinmeyen bir değer olduğu gibi basılır,
     * bilinenler Türkçe karşılığıyla.
     *
     * Ham değer API'de ve Chroma'ya giden üstveride AYNEN durur: dışarıdaki
     * istemci etiketi değil, değeri bekliyor.
     */
    public function kaynakEtiketi(): string
    {
        $ham = (string) $this->kaynak;

        return match ($ham) {
            'manuel', 'elle' => 'Elle eklendi',
            'kurulum', 'ilk-kurulum' => 'Kurulum seti',
            'chroma' => 'İçe aktarıldı',
            'satis-ekibi' => 'Satış ekibi',
            'egitim-genel' => 'Genel teknik eğitim',
            default => $ham,
        };
    }

    /** Henüz indekslenmemiş ya da indekslendikten sonra değişmiş kayıtlar. */
    public function scopeIndekslenmemis(Builder $query): void
    {
        $query->whereNull('indekslendi')->orWhereColumn('updated_at', '>', 'indekslendi');
    }

    /**
     * Basit metin araması.
     *
     * Chroma erişilemediğinde teklif üretimi tamamen bağlamsız kalmasın diye
     * var: anlamsal arama değil ama hiç bağlam olmamasından iyidir.
     */
    public function scopeMetinde(Builder $query, string $arama): void
    {
        $query->where(fn (Builder $i) => $i
            ->where('metin', 'like', "%{$arama}%")
            ->orWhere('baslik', 'like', "%{$arama}%")
            ->orWhere('kategori', 'like', "%{$arama}%"));
    }
}
