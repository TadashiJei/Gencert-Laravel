<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'content',
        'orientation',
        'paper_size',
        'custom_fields',
        'is_active',
    ];

    protected $casts = [
        'content' => 'json',
        'custom_fields' => 'json',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function settings()
    {
        return $this->hasOne(TemplateSetting::class, 'template_id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }

    public function bulkJobs()
    {
        return $this->hasMany(BulkCertificateJob::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper Methods
    public function hasQrCode()
    {
        return $this->settings->enable_qr_code ?? false;
    }

    public function hasExpiration()
    {
        return $this->settings->enable_expiration ?? false;
    }

    public function hasAutoRenewal()
    {
        return $this->settings->enable_auto_renewal ?? false;
    }

    public function hasRevocation()
    {
        return $this->settings->enable_revocation ?? false;
    }

    public function supportsMultiLanguage()
    {
        return $this->settings->enable_multi_language ?? false;
    }

    public function getSupportedLanguages()
    {
        return $this->settings->supported_languages ?? ['en'];
    }
}
