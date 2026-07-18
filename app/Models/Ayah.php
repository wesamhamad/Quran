<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ayah extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(Word::class)->orderBy('position');
    }

    public function tafsirTexts(): HasMany
    {
        return $this->hasMany(TafsirText::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function audioFiles(): HasMany
    {
        return $this->hasMany(AudioFile::class);
    }
}
