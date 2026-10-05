<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    use HasFactory;

    protected $with = ['bannerImages'];

    protected $fillable = [
        'name',
        'slug',
        'icon_key',
        'banner_image_path',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function bannerImages(): HasMany
    {
        return $this->hasMany(SportBannerImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MahjMatch::class);
    }
}
