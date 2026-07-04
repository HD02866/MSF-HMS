<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name'  => $this->faker->name(),
            'username'   => $this->faker->unique()->userName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'password'   => 'password',
            'phone'      => $this->faker->phoneNumber(),
            'is_active'  => true,
            'role_id'    => Role::inRandomOrder()->first()?->id ?? Role::factory(),
            'department_id' => Department::inRandomOrder()->first()?->id,
        ];
    }
}
