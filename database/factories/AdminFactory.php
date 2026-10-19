<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AdminFactory extends Factory
{
    protected $model = Admin::class;
    public function definition(): array
    {
        return ['name' => $this->faker->name(), 'username' => $this->faker->unique()->userName(), 'email' => $this->faker->unique()->safeEmail(), 'password' => bcrypt(Str::random(32)), 'is_super' => false];
    }
}
