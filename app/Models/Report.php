<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'config',
        'data',
        'schedule',
        'next_run',
        'last_run',
        'created_by'
    ];

    protected $casts = [
        'config' => 'array',
        'data' => 'array',
        'schedule' => 'array',
        'next_run' => 'datetime',
        'last_run' => 'datetime'
    ];

    /**
     * Get the user who created the report
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for reports due to run
     */
    public function scopeDueToRun($query)
    {
        return $query->whereNotNull('schedule')
            ->whereNotNull('next_run')
            ->where('next_run', '<=', now());
    }

    /**
     * Update next run time based on schedule
     */
    public function updateNextRun()
    {
        if (!$this->schedule) {
            return;
        }

        $frequency = $this->schedule['frequency'];
        $now = now();

        switch ($frequency) {
            case 'daily':
                $this->next_run = $now->addDay()->setTime(
                    $this->schedule['hour'] ?? 0,
                    $this->schedule['minute'] ?? 0
                );
                break;
            case 'weekly':
                $this->next_run = $now->next($this->schedule['day'] ?? 1)->setTime(
                    $this->schedule['hour'] ?? 0,
                    $this->schedule['minute'] ?? 0
                );
                break;
            case 'monthly':
                $this->next_run = $now->addMonth()->setDay($this->schedule['day'] ?? 1)->setTime(
                    $this->schedule['hour'] ?? 0,
                    $this->schedule['minute'] ?? 0
                );
                break;
        }

        $this->save();
    }

    /**
     * Check if report is scheduled
     */
    public function isScheduled()
    {
        return !is_null($this->schedule) && !is_null($this->next_run);
    }

    /**
     * Get report status
     */
    public function getStatus()
    {
        if (!$this->isScheduled()) {
            return 'one-time';
        }

        if ($this->next_run > now()) {
            return 'scheduled';
        }

        return 'overdue';
    }

    /**
     * Get formatted schedule description
     */
    public function getScheduleDescription()
    {
        if (!$this->schedule) {
            return 'No schedule';
        }

        $frequency = $this->schedule['frequency'];
        $time = sprintf(
            '%02d:%02d',
            $this->schedule['hour'] ?? 0,
            $this->schedule['minute'] ?? 0
        );

        switch ($frequency) {
            case 'daily':
                return "Daily at {$time}";
            case 'weekly':
                $day = $this->schedule['day'] ?? 1;
                return "Weekly on " . date('l', strtotime("Sunday +{$day} days")) . " at {$time}";
            case 'monthly':
                $day = $this->schedule['day'] ?? 1;
                return "Monthly on day {$day} at {$time}";
            default:
                return 'Invalid schedule';
        }
    }
}
