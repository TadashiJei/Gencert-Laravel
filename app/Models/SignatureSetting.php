<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignatureSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'signature_path',
        'mime_type',
        'is_default',
        'position',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'position' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function certificateSignatures()
    {
        return $this->hasMany(CertificateSignature::class);
    }

    // Helper Methods
    public function getPosition()
    {
        return $this->position ?? [
            'x' => 0,
            'y' => 0,
        ];
    }

    public function makeDefault()
    {
        // Remove default from other signatures
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function getFullPath()
    {
        return storage_path('app/signatures/' . $this->signature_path);
    }
}
