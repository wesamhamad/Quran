<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AyahSimilarity extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'show_context' => 'boolean',
    ];

    /** الآية المصدر (المرساة). */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class, 'ayah_id');
    }

    /** الآية المشابهة (المرساة). */
    public function similar(): BelongsTo
    {
        return $this->belongsTo(Ayah::class, 'similar_ayah_id');
    }
}
