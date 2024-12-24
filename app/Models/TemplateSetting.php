<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'enable_qr_code',
        'qr_code_settings',
        'enable_expiration',
        'expiration_days',
        'enable_auto_renewal',
        'renewal_days_before',
        'enable_revocation',
        'enable_multi_language',
        'supported_languages',
    ];

    protected $casts = [
        'enable_qr_code' => 'boolean',
        'qr_code_settings' => 'json',
        'enable_expiration' => 'boolean',
        'expiration_days' => 'integer',
        'enable_auto_renewal' => 'boolean',
        'renewal_days_before' => 'integer',
        'enable_revocation' => 'boolean',
        'enable_multi_language' => 'boolean',
        'supported_languages' => 'json',
    ];

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    // Helper Methods
    public function getQrCodePosition()
    {
        return $this->qr_code_settings['position'] ?? [
            'x' => 0,
            'y' => 0,
        ];
    }

    public function getQrCodeSize()
    {
        return $this->qr_code_settings['size'] ?? 100;
    }

    public function getExpirationDate($fromDate = null)
    {
        if (!$this->enable_expiration) {
            return null;
        }

        $fromDate = $fromDate ?? now();
        return $fromDate->addDays($this->expiration_days);
    }

    public function getRenewalDate($expirationDate)
    {
        if (!$this->enable_auto_renewal) {
            return null;
        }

        return $expirationDate->subDays($this->renewal_days_before);
    }

    public function isLanguageSupported($language)
    {
        if (!$this->enable_multi_language) {
            return $language === 'en';
        }

        return in_array($language, $this->supported_languages ?? ['en']);
    }
}
