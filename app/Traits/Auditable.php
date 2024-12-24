<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            AuditLog::log('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $oldValues = array_intersect_key(
                $model->getOriginal(),
                $model->getChanges()
            );
            
            $newValues = array_intersect_key(
                $model->getAttributes(),
                $model->getChanges()
            );

            AuditLog::log('updated', $model, $oldValues, $newValues);
        });

        static::deleted(function ($model) {
            AuditLog::log('deleted', $model, $model->getAttributes(), null);
        });
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
