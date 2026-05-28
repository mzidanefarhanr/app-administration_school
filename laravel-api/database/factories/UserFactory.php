<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;
    /**
     * The current status_user_id being used by the factory.
     */
    protected static ?string $status_user_id;
    /**
     * The current type_user_id being used by the factory.
     */
    protected static ?string $type_user_id;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'username'          => fake()->name(),
            'password'          => static::$password ??= Hash::make('password'),
            'type_user_id'      => static::$type_user_id ??= 2,
            'status_user_id'    => static::$status_user_id ??= 1,
            'first_login_at'    => false,
            'status_active'     => false,
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
