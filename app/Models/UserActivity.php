<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_type',
        'metadata',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Activity types
     */
    const TYPE_LOGIN = 'login';
    const TYPE_LOGOUT = 'logout';
    const TYPE_CREATE_CERTIFICATE = 'create_certificate';
    const TYPE_UPDATE_CERTIFICATE = 'update_certificate';
    const TYPE_REVOKE_CERTIFICATE = 'revoke_certificate';
    const TYPE_CREATE_TEMPLATE = 'create_template';
    const TYPE_UPDATE_TEMPLATE = 'update_template';
    const TYPE_DELETE_TEMPLATE = 'delete_template';
    const TYPE_GENERATE_REPORT = 'generate_report';
    const TYPE_EXPORT_REPORT = 'export_report';
    const TYPE_API_ACCESS = 'api_access';

    /**
     * Get the user who performed the activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get activity description
     */
    public function getDescription()
    {
        switch ($this->activity_type) {
            case self::TYPE_LOGIN:
                return 'Logged in';
            case self::TYPE_LOGOUT:
                return 'Logged out';
            case self::TYPE_CREATE_CERTIFICATE:
                return 'Created certificate ' . ($this->metadata['certificate_number'] ?? '');
            case self::TYPE_UPDATE_CERTIFICATE:
                return 'Updated certificate ' . ($this->metadata['certificate_number'] ?? '');
            case self::TYPE_REVOKE_CERTIFICATE:
                return 'Revoked certificate ' . ($this->metadata['certificate_number'] ?? '');
            case self::TYPE_CREATE_TEMPLATE:
                return 'Created template ' . ($this->metadata['template_name'] ?? '');
            case self::TYPE_UPDATE_TEMPLATE:
                return 'Updated template ' . ($this->metadata['template_name'] ?? '');
            case self::TYPE_DELETE_TEMPLATE:
                return 'Deleted template ' . ($this->metadata['template_name'] ?? '');
            case self::TYPE_GENERATE_REPORT:
                return 'Generated report ' . ($this->metadata['report_name'] ?? '');
            case self::TYPE_EXPORT_REPORT:
                return 'Exported report ' . ($this->metadata['report_name'] ?? '');
            case self::TYPE_API_ACCESS:
                return 'API access: ' . ($this->metadata['endpoint'] ?? '');
            default:
                return $this->activity_type;
        }
    }

    /**
     * Get activity icon
     */
    public function getIcon()
    {
        switch ($this->activity_type) {
            case self::TYPE_LOGIN:
                return 'login';
            case self::TYPE_LOGOUT:
                return 'logout';
            case self::TYPE_CREATE_CERTIFICATE:
                return 'certificate-plus';
            case self::TYPE_UPDATE_CERTIFICATE:
                return 'certificate-edit';
            case self::TYPE_REVOKE_CERTIFICATE:
                return 'certificate-x';
            case self::TYPE_CREATE_TEMPLATE:
                return 'template-plus';
            case self::TYPE_UPDATE_TEMPLATE:
                return 'template-edit';
            case self::TYPE_DELETE_TEMPLATE:
                return 'template-x';
            case self::TYPE_GENERATE_REPORT:
                return 'report-generate';
            case self::TYPE_EXPORT_REPORT:
                return 'report-export';
            case self::TYPE_API_ACCESS:
                return 'api';
            default:
                return 'activity';
        }
    }

    /**
     * Get activity color
     */
    public function getColor()
    {
        switch ($this->activity_type) {
            case self::TYPE_LOGIN:
            case self::TYPE_CREATE_CERTIFICATE:
            case self::TYPE_CREATE_TEMPLATE:
                return 'success';
            case self::TYPE_LOGOUT:
            case self::TYPE_DELETE_TEMPLATE:
            case self::TYPE_REVOKE_CERTIFICATE:
                return 'danger';
            case self::TYPE_UPDATE_CERTIFICATE:
            case self::TYPE_UPDATE_TEMPLATE:
                return 'warning';
            case self::TYPE_GENERATE_REPORT:
            case self::TYPE_EXPORT_REPORT:
                return 'info';
            case self::TYPE_API_ACCESS:
                return 'primary';
            default:
                return 'secondary';
        }
    }

    /**
     * Scope for recent activities
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->with('user')
            ->latest()
            ->limit($limit);
    }

    /**
     * Scope for user's activities
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for activities by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope for activities within date range
     */
    public function scopeWithinDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
