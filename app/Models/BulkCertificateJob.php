<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkCertificateJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_id',
        'file_path',
        'field_mapping',
        'total_records',
        'processed_records',
        'failed_records',
        'error_log',
        'status',
    ];

    protected $casts = [
        'field_mapping' => 'json',
        'error_log' => 'json',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'failed_records' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    // Helper Methods
    public function getProgress()
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return ($this->processed_records / $this->total_records) * 100;
    }

    public function addError($row, $message)
    {
        $errors = $this->error_log ?? [];
        $errors[] = [
            'row' => $row,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ];

        $this->update([
            'error_log' => $errors,
            'failed_records' => $this->failed_records + 1,
        ]);
    }

    public function incrementProcessed()
    {
        $this->increment('processed_records');

        if ($this->processed_records >= $this->total_records) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        }
    }

    public function getFilePath()
    {
        return storage_path('app/bulk-jobs/' . $this->file_path);
    }
}
