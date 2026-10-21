<?php

namespace Tests\Unit;

use App\Models\KnowledgeRecord;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sınıflar İngilizce adlandırılırken etkinlik kaydındaki eski sınıf adı satırları
 * (subject_type / konu_type) ve zincir özetleri bozulmamalı.
 */
class LegacyMorphAliasTest extends TestCase
{
    public static function renamed(): array
    {
        return [
            ['App\\Models\\BilgiKaydi', KnowledgeRecord::class],
            ['App\\Models\\Rol', Role::class],
        ];
    }

    #[DataProvider('renamed')]
    public function test_old_name_resolves_and_new_rows_keep_old_name(string $old, string $new): void
    {
        $this->assertSame($new, Relation::getMorphedModel($old));
        $this->assertSame($old, (new $new)->getMorphClass());
    }
}
