<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reciter extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function audioFiles(): HasMany
    {
        return $this->hasMany(AudioFile::class);
    }
}
