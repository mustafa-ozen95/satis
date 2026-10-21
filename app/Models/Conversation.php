<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'ilk_talep', 'durum', 'hata_mesaji', 'okundu_at'];

    protected function casts(): array
    {
        return ['okundu_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Bu konuşmada OKUNMAMIŞ bir yanıt var mı?
     *
     * Kullanıcı teklif isteyip başka işe geçiyor; metin hazır olduğunda
     * listede bir şey değişmezse yanıtın geldiği ancak konuşma açılınca fark
     * ediliyor.
     *
     * Ölçüt ZAMAN: son yapay zeka yanıtı, konuşmanın son açılışından sonra
     * geldiyse okunmamıştır. "Okundu" bayrağı aynı konuşmaya gelen İKİNCİ
     * yanıtı ayırt edemezdi.
     *
     * Hazır olmayan (hâlâ üretilen ya da hata almış) konuşma okunmamış
     * sayılmaz: orada beklenen bir yanıt yok, durumu zaten kendi rozetiyle
     * görünüyor.
     */
    public function okunmamisMi(): bool
    {
        if ($this->durum !== 'hazir') {
            return false;
        }

        /*
         * `reorder` ŞART: messages() ilişkisi kendi sıralamasını taşıyor
         * (created_at ARTAN) ve üstüne latest() eklemek iki sıralama üretip
         * EN ESKİ yanıtı döndürüyordu — okunmamışlık sessizce yanlış
         * hesaplanıyordu.
         */
        $sonYanit = $this->messages()
            ->where('role', 'assistant')
            ->reorder('created_at', 'desc')
            ->value('created_at');

        if ($sonYanit === null) {
            return false;
        }

        return $this->okundu_at === null || $this->okundu_at->lt($sonYanit);
    }

    /** Konuşma açıldı: bu andan öncesi okunmuş sayılır. */
    public function okunduIsaretle(): void
    {
        $this->forceFill(['okundu_at' => now()])->save();
    }
}
