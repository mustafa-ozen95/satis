<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sınamalar kurulumun şirket dosyalarını (storage/app/company/) GÖRMEZ:
     * depo bir kurulumun içinde koşturulursa canlı istem ya da site listesi
     * sınamaya karışmasın. Dosya isteyen sınama kendi geçici dosyasını verir.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['company.ad' => '', 'company.site' => '', 'company.istem_dosyasi' => '', 'company.site_sayfalari_dosyasi' => '']);
    }
}
