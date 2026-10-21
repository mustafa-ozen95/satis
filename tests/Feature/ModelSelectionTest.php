<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teklif motoru ayarları: model LİSTEDEN seçilir.
 *
 * Serbest metin kutusuydu ve bir harf hatası ancak ilk teklifte — hem de
 * kullanıcının karşısında — patlıyordu. Ayrıca yedek modelin listede olması,
 * "yoğunluk" gününde tek tıkla geçebilmek demek.
 */
class ModelSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ayar_ekraninda_modeller_secenek_olarak_gelir(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.ayarlar.index', ['grup' => 'teklif']))
            ->assertOk()
            ->assertSee('gemini-3.6-flash')
            ->assertSee('gemini-3.7-flash')
            ->assertSee('gemini-3.8-flash');
    }

    public function test_secilen_model_kaydedilir(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.ayarlar.update'), [
                'group' => 'teklif',
                'services__gemini__model' => 'gemini-3.7-flash',
                'services__gemini__yedek_model' => 'gemini-3.6-flash',
                'services__groq__model' => 'openai/gpt-oss-120b',
                'services__llm__timeout' => 120,
                'services__llm__retry' => 3,
                'services__llm__temperature' => '0.2',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('gemini-3.7-flash', app(SettingsRepository::class)->current('services.gemini.model'));
    }

    /** Listede olmayan bir model kabul edilmez. */
    public function test_listede_olmayan_model_reddedilir(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.ayarlar.index', ['grup' => 'teklif']))
            ->put(route('admin.ayarlar.update'), [
                'group' => 'teklif',
                'services__gemini__model' => 'uydurma-model',
                'services__groq__model' => 'openai/gpt-oss-120b',
                'services__llm__timeout' => 120,
                'services__llm__retry' => 3,
                'services__llm__temperature' => '0.2',
            ])
            ->assertSessionHasErrors();
    }
}
