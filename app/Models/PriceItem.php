<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category', 'name', 'unit', 'price', 'currency', 'kdv_dahil', 'aktif', 'notlar',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'kdv_dahil' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    /** Ekranda görünen fiyat: "1.500 TL" (gereksiz sıfırlar atılır). */
    public function displayPrice(): string
    {
        return rtrim(rtrim(number_format((float) $this->price, 2, ',', '.'), '0'), ',').' '.$this->currency;
    }

    /** Yerinde düzenlenecek ham değer: "1500" (binlik ayracı yok). */
    public function rawPrice(): string
    {
        return rtrim(rtrim(number_format((float) $this->price, 2, '.', ''), '0'), '.');
    }

    public function scopeActive($query)
    {
        return $query->where('aktif', true);
    }
}
