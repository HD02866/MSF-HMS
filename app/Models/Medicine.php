<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    protected $fillable = [
        'name',
        'generic_name',
        'category',
        'form',
        'unit',
        'unit_price',
        'quantity_in_stock',
        'minimum_stock_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'          => 'decimal:2',
            'quantity_in_stock'   => 'integer',
            'minimum_stock_level' => 'integer',
            'is_active'           => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_in_stock', '<=', 'minimum_stock_level');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(PharmacyRequestItem::class);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity_in_stock <= $this->minimum_stock_level;
    }

    public function getInStockAttribute(): bool
    {
        return $this->quantity_in_stock > 0;
    }
}
