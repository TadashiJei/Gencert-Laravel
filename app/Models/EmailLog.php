<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'certificate_id',
        'recipient_email',
        'subject',
        'status',
        'error_message',
        'sent_at',
        'opened_at',
        'clicked_at',
        'tracking_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function markAsOpened($ipAddress = null, $userAgent = null)
    {
        $this->update([
            'status' => 'opened',
            'opened_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function markAsClicked($ipAddress = null, $userAgent = null)
    {
        $this->update([
            'status' => 'clicked',
            'clicked_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
