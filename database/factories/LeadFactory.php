<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => 'LD-'.$this->faker->unique()->numerify('######'),
            'created_at' => $this->faker->dateTimeBetween('-1 year'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone' => $this->faker->numerify('380#########'),
            'email' => $this->faker->unique()->safeEmail(),
            'city' => $this->faker->city(),
            'source' => 'Facebook Ads',
            'utm_campaign' => 'campaign_'.$this->faker->word(),
            'product' => $this->faker->word(),
            'budget_uah' => $this->faker->numberBetween(1000, 50000),
            'status' => 'new',
            'manager' => $this->faker->name(),
            'comment' => null,
            'next_contact_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ];
    }
}
