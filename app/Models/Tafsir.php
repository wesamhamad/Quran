<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tafsir extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function texts(): HasMany
    {
        return $this->hasMany(TafsirText::class);
    }
}
