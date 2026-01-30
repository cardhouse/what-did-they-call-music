<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Album;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template TModel of \App\Models\Album
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class AlbumFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Album::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->numberBetween(1, 100),
            'name' => fake()->sentence(3),
            'release_date' => fake()->date(),
            'cover_art_url' => fake()->imageUrl(640, 640, 'music'),
            'type' => 'regular',
            'spotify_url' => null,
            'apple_music_url' => null,
            'affiliate_url' => null,
        ];
    }
}
