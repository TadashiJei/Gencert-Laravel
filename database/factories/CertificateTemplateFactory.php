<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'layout' => json_encode([
                'title' => [
                    'x' => $this->faker->numberBetween(100, 500),
                    'y' => $this->faker->numberBetween(100, 300),
                    'font_size' => $this->faker->numberBetween(20, 40),
                    'font_family' => $this->faker->randomElement(['Arial', 'Times New Roman', 'Helvetica'])
                ],
                'body' => [
                    'x' => $this->faker->numberBetween(100, 500),
                    'y' => $this->faker->numberBetween(400, 600),
                    'font_size' => $this->faker->numberBetween(12, 18),
                    'font_family' => $this->faker->randomElement(['Arial', 'Times New Roman', 'Helvetica'])
                ],
                'footer' => [
                    'x' => $this->faker->numberBetween(100, 500),
                    'y' => $this->faker->numberBetween(700, 800),
                    'font_size' => $this->faker->numberBetween(10, 14),
                    'font_family' => $this->faker->randomElement(['Arial', 'Times New Roman', 'Helvetica'])
                ]
            ]),
            'variables' => json_encode([
                'recipient_name' => 'string',
                'course_name' => 'string',
                'completion_date' => 'date',
                'certificate_id' => 'string'
            ]),
            'background_image' => $this->faker->imageUrl(800, 600),
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft']),
            'created_by' => User::factory()
        ];
    }
}
