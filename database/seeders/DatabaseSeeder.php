<?php

namespace Database\Seeders;

use App\Models\KnowledgeRecord;
use App\Models\PriceItem;
use App\Models\Role;
use App\Models\User;
use App\Services\KnowledgeBaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Kurulumda otomatik doldurma: ilk admin hesabı + fiyat listesi + (varsa Gemini
     * anahtarıyla) bilgi bankası, sadece tablolar boşsa doldurulur.
     */
    public function run(KnowledgeBaseService $knowledgeBase): void
    {
        /*
         * E-POSTA YA DA PAROLA VERİLMEMİŞSE HESAP AÇILMAZ. Varsayılan bir
         * adres, kurulumu yapanın sahip olmadığı bir hesabın; varsayılan bir
         * parola, herkesin bildiği bir girişin açılması demek olurdu. Kardeş
         * panellerde de aynı kural var. Konteynerde başlatıcı boş parolayı
         * rastgele üretir.
         */
        if (User::count() === 0 && filled(config('panel.email')) && ! filled(config('panel.password'))) {
            $this->command?->warn('PANEL_PASSWORD verilmedi; ilk yönetici hesabı açılmadı.');
        }

        if (User::count() === 0 && filled(config('panel.email')) && filled(config('panel.password'))) {
            User::create([
                'name' => config('panel.name'),
                'email' => config('panel.email'),
                'password' => Hash::make(config('panel.password')),
                'rol_id' => Role::where('slug', 'admin')->value('id'),
            ]);

            $this->command?->info('İlk admin kullanıcısı otomatik oluşturuldu: '.config('panel.email'));
        }

        if (PriceItem::count() === 0) {
            $this->call(PriceItemSeeder::class);
            $this->command?->info('Fiyat motoru boştu, '.PriceItem::count().' kalem otomatik yüklendi.');
        }

        /*
         * Bilgi bankası VERİTABANINDA: Gemini anahtarı olmasa da kayıtlar
         * yazılır, yalnızca indeks eksik kalır ve `knowledge:index` onu
         * sonradan kurar.
         */
        try {
            if (KnowledgeRecord::query()->doesntExist()) {
                $this->call(KnowledgeBaseSeeder::class);
                $this->command?->info('Bilgi bankası boştu, otomatik yüklendi.');
            }
        } catch (Throwable $e) {
            $this->command?->warn('Bilgi bankası yüklenirken hata: '.$e->getMessage());
        }
    }
}
