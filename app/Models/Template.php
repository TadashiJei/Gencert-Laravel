<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'category',
        'is_public',
        'metadata',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderBy('created_at', 'desc');
    }

    public function latestVersion()
    {
        return $this->versions()->latest()->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
