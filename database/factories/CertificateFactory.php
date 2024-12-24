<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition()
    {
        return [
            'template_id' => CertificateTemplate::factory(),
            'user_id' => User::factory(),
            'certificate_number' => 'CERT-' . $this->faker->unique()->randomNumber(6),
            'recipient_name' => $this->faker->name(),
            'recipient_email' => $this->faker->safeEmail(),
            'custom_fields' => json_encode([
                'field1' => $this->faker->word(),
                'field2' => $this->faker->word()
            ]),
            'language' => 'en',
            'issue_date' => now(),
            'expiry_date' => now()->addYear(),
            'status' => 'active',
            'auto_renewal' => false,
            'file_path' => 'certificates/' . $this->faker->uuid() . '.pdf',
            'created_by' => User::factory()
        ];
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'expired',
                'expiry_date' => now()->subDays(rand(1, 30))
            ];
        });
    }

    public function revoked()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'revoked'
            ];
        });
    }
}
