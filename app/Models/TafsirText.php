<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TafsirText extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function tafsir(): BelongsTo
    {
        return $this->belongsTo(Tafsir::class);
    }

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
