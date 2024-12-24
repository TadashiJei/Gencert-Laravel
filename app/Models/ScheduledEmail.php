<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledEmail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email_setting_id',
        'recipient_email',
        'recipient_name',
        'email_data',
        'scheduled_at',
        'sent_at',
        'status',
        'error_message'
    ];

    protected $casts = [
        'email_data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime'
    ];

    public function emailSetting()
    {
        return $this->belongsTo(EmailSetting::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
