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

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
