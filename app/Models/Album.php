<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Album extends Model
{
    protected $fillable = [
        'number',
        'name',
        'release_date',
        'cover_art_url',
        'type',
        'spotify_url',
        'apple_music_url',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'number' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Song, $this>
     */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)
            ->withPivot(['track_number', 'chart_position'])
            ->withTimestamps()
            ->orderBy('album_song.track_number');
    }

    /**
     * @param  Builder<Album>  $query
     * @return Builder<Album>
     */
    public function scopeReleasedByDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('release_date', '<=', $date);
    }

    /**
     * @param  Builder<Album>  $query
     * @return Builder<Album>
     */
    public function scopeMostRecentByDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('release_date', '<=', $date)
            ->orderBy('release_date', 'desc');
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->number) {
            return "NOW {$this->number}";
        }

        return $this->name;
    }

    public function getFormattedReleaseDateAttribute(): string
    {
        if (! $this->release_date) {
            return '';
        }

        return Carbon::parse($this->release_date)->format('F Y');
    }

    public function getProxiedCoverArtUrlAttribute(): ?string
    {
        if (! $this->cover_art_url) {
            return null;
        }

        // Only proxy URLs from Fandom/Wikia
        if (str_contains($this->cover_art_url, 'wikia.nocookie.net')) {
            return route('image.proxy', ['url' => base64_encode($this->cover_art_url)]);
        }

        return $this->cover_art_url;
    }
}
