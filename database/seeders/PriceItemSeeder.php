<?php

namespace Database\Seeders;

use App\Models\PriceItem;
use Illuminate\Database\Seeder;

/**
 * Yeni bir kurulumun fiyat motoru boşsa yüklenen ÖRNEK kalemler.
 *
 * Gerçek fiyat listesi depoda DURMAZ: veritabanındadır ve panelden (Fiyatlar
 * ekranı) yönetilir. Buradaki üç kalem yalnız kurulumun ilk açılışta
 * çalıştığını gösterir; gerçek kullanıma geçmeden silin ya da değiştirin.
 */
class PriceItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $item) {
            PriceItem::create(array_merge([
                'unit' => 'ay',
                'currency' => 'TL',
                'kdv_dahil' => false,
                'aktif' => true,
                'notlar' => null,
            ], $item));
        }
    }

    protected function items(): array
    {
        return [
            ['category' => 'Örnek Sunucu', 'name' => 'Örnek paket: 4 çekirdek, 8 GB RAM, 100 GB SSD', 'price' => 1000, 'notlar' => 'Örnek kalem: gerçek fiyat listesiyle değiştirin.'],
            ['category' => 'Örnek Sunucu', 'name' => 'Ek RAM (8 GB)', 'price' => 100],
            ['category' => 'Örnek IP', 'name' => '/29 IP bloğu (8 IP)', 'price' => 200],
        ];
    }
}
