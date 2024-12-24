<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ScheduledEmail;

class EmailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_name',
        'subject',
        'body_template',
        'variables',
        'is_active',
        'trigger_event'
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean'
    ];

    public function scheduledEmails()
    {
        return $this->hasMany(ScheduledEmail::class);
    }
}
