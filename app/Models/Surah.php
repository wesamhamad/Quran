<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Surah extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'bismillah_pre' => 'boolean',
    ];

    public function ayahs(): HasMany
    {
        return $this->hasMany(Ayah::class)->orderBy('number_in_surah');
    }
}
