<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function log($event, $auditable, $oldValues = null, $newValues = null)
    {
        return static::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function getChangesAttribute()
    {
        $changes = [];

        if ($this->old_values && $this->new_values) {
            foreach ($this->new_values as $key => $value) {
                if (!array_key_exists($key, $this->old_values) || $this->old_values[$key] !== $value) {
                    $changes[$key] = [
                        'old' => $this->old_values[$key] ?? null,
                        'new' => $value,
                    ];
                }
            }
        }

        return $changes;
    }
}
