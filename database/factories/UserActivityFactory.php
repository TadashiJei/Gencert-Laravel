<?php

namespace Database\Factories;

use App\Models\UserActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserActivityFactory extends Factory
{
    protected $model = UserActivity::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'activity_type' => $this->faker->randomElement([
                'create_certificate',
                'verify_certificate',
                'revoke_certificate',
                'update_certificate',
                'login',
                'logout'
            ]),
            'metadata' => json_encode([
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'details' => $this->faker->sentence()
            ])
        ];
    }
}
