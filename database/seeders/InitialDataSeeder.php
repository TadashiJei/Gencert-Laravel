<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailSetting;
use App\Models\ProjectSetting;

class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default email templates
        EmailSetting::create([
            'template_name' => 'certificate_issued',
            'subject' => 'Your Certificate Has Been Issued',
            'body_template' => 'Dear {{recipient_name}},

Your certificate has been issued successfully. You can download it using the following link:
{{certificate_link}}

Best regards,
{{organization_name}}',
            'variables' => [
                'recipient_name' => 'string',
                'certificate_link' => 'string',
                'organization_name' => 'string'
            ],
            'trigger_event' => 'certificate.issued',
            'is_active' => true
        ]);

        EmailSetting::create([
            'template_name' => 'certificate_expiring',
            'subject' => 'Your Certificate is About to Expire',
            'body_template' => 'Dear {{recipient_name}},

This is a reminder that your certificate will expire on {{expiry_date}}.

Best regards,
{{organization_name}}',
            'variables' => [
                'recipient_name' => 'string',
                'expiry_date' => 'string',
                'organization_name' => 'string'
            ],
            'trigger_event' => 'certificate.expiring',
            'is_active' => true
        ]);

        // Create default project settings
        ProjectSetting::set('organization_name', 'CertificateHub', 'string', 'general', 'Organization name displayed on certificates and emails', true);
        ProjectSetting::set('certificate_validity_days', '365', 'integer', 'certificates', 'Default validity period for certificates in days', true);
        ProjectSetting::set('enable_email_notifications', 'true', 'boolean', 'email', 'Enable/disable email notifications', true);
        ProjectSetting::set('expiry_notification_days', '30', 'integer', 'email', 'Days before expiry to send notification', true);
        ProjectSetting::set('default_certificate_template', '1', 'integer', 'certificates', 'Default template ID for new certificates', true);
    }
}
