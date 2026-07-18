<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioFile extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'segments' => 'array',
    ];

    public function reciter(): BelongsTo
    {
        return $this->belongsTo(Reciter::class);
    }

    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
