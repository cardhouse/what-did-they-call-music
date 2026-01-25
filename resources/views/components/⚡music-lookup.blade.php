<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Song;
use App\Services\MusicLookupService;
use App\Services\SpotifyMatchService;
use App\Services\FandomScraper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

new class extends Component
{
    public string $selectedDate = '';
    
    #[Locked]
    public ?string $minAlbumReleaseDate = null;
    
    #[Locked]
    public ?string $maxAlbumReleaseDate = null;
    
    public bool $searched = false;
    public string $errorMessage = '';

    /** @var array<int, string|null> Song ID => Spotify track ID (null = not found) */
    public array $resolvedSpotifyIds = [];

    /** @var array<int, bool> Song IDs currently being resolved */
    public array $pendingSpotifyLookups = [];

    /** @var array<int, string|null> Album ID => Cover art URL (null = not found) */
    public array $resolvedAlbumArtwork = [];

    public function mount(): void
    {
        $stats = $this->stats;

        $firstDate = $stats['date_range']['first'] ?? null;
        $latestDate = $stats['date_range']['latest'] ?? null;

        $this->minAlbumReleaseDate = $firstDate?->format('Y-m-d');
        $this->maxAlbumReleaseDate = $latestDate?->format('Y-m-d');

        $defaultDate = now()->startOfDay();

        if ($latestDate && $defaultDate->gt($latestDate)) {
            $defaultDate = $latestDate->copy();
        }

        if ($firstDate && $defaultDate->lt($firstDate)) {
            $defaultDate = $firstDate->copy();
        }

        $this->selectedDate = $defaultDate->format('Y-m-d');
    }

    #[Computed]
    public function stats(): array
    {
        return app(MusicLookupService::class)->getCollectionStats();
    }

    #[Computed]
    public function albums(): Collection
    {
        if (!$this->searched || !$this->selectedDate) {
            return collect();
        }

        try {
            $date = Carbon::parse($this->selectedDate);
            return app(MusicLookupService::class)->findAlbumsForDate($date);
        } catch (\Exception $e) {
            return collect();
        }
    }

    public function search(): void
    {
        $rules = [
            'selectedDate' => ['required', 'date'],
        ];

        $messages = [];

        if ($this->minAlbumReleaseDate) {
            $rules['selectedDate'][] = 'after_or_equal:'.$this->minAlbumReleaseDate;
            $messages['selectedDate.after_or_equal'] = 'Pick a date on or after '.Carbon::parse($this->minAlbumReleaseDate)->format('F Y').'.';
        }

        if ($this->maxAlbumReleaseDate) {
            $rules['selectedDate'][] = 'before_or_equal:'.$this->maxAlbumReleaseDate;
            $messages['selectedDate.before_or_equal'] = 'Pick a date on or before '.Carbon::parse($this->maxAlbumReleaseDate)->format('F Y').'.';
        }

        $this->validate($rules, $messages);

        $this->reset(['errorMessage', 'resolvedSpotifyIds', 'pendingSpotifyLookups', 'resolvedAlbumArtwork']);
        $this->searched = true;

        if ($this->albums->isEmpty()) {
            $this->errorMessage = "No NOW albums found for that date. This shouldn't happen!";
            return;
        }

        $this->preloadExistingSpotifyIds();
        $this->preloadExistingAlbumArtwork();
    }

    /**
     * Pre-populate resolvedSpotifyIds from database to avoid unnecessary API calls.
     */
    protected function preloadExistingSpotifyIds(): void
    {
        foreach ($this->albums as $album) {
            foreach ($album->songs as $song) {
                if ($song->spotify_id) {
                    $this->resolvedSpotifyIds[$song->id] = $song->spotify_id;
                } elseif ($song->spotify_lookup_at) {
                    $this->resolvedSpotifyIds[$song->id] = null;
                } elseif ($song->primaryArtists->isEmpty()) {
                    $this->resolvedSpotifyIds[$song->id] = null;
                }
            }
        }
    }

    /**
     * Pre-populate resolvedAlbumArtwork from database to avoid unnecessary scraping.
     */
    protected function preloadExistingAlbumArtwork(): void
    {
        foreach ($this->albums as $album) {
            if ($album->cover_art_url) {
                $this->resolvedAlbumArtwork[$album->id] = $album->cover_art_url;
            }
        }
    }

    public function resolveSpotifyForSong(int $songId): void
    {
        if (array_key_exists($songId, $this->resolvedSpotifyIds)) {
            return;
        }

        if (!$this->albums) {
            return;
        }

        $targetSong = null;
        foreach ($this->albums as $album) {
            $targetSong = $album->songs->firstWhere('id', $songId);
            if ($targetSong) {
                break;
            }
        }

        if (!$targetSong) {
            return;
        }

        if ($targetSong->spotify_id) {
            $this->resolvedSpotifyIds[$songId] = $targetSong->spotify_id;
            return;
        }

        $primaryArtist = $targetSong->primaryArtists->first();

        if (!$primaryArtist) {
            $this->resolvedSpotifyIds[$songId] = null;
            return;
        }

        $this->pendingSpotifyLookups[$songId] = true;

        $trackId = app(SpotifyMatchService::class)->findTrackId($targetSong->title, $primaryArtist->name);

        unset($this->pendingSpotifyLookups[$songId]);

        $this->resolvedSpotifyIds[$songId] = $trackId;

        $targetSong->spotify_id = $trackId;
        $targetSong->spotify_lookup_at = now();
        $targetSong->saveQuietly();
    }

    public function getSpotifyUrlForSong(int $songId): ?string
    {
        $trackId = $this->resolvedSpotifyIds[$songId] ?? null;
        return $trackId ? "https://open.spotify.com/track/{$trackId}" : null;
    }

    public function playTrack(int $songId): void
    {
        $trackId = $this->resolvedSpotifyIds[$songId] ?? null;

        if (!$trackId) {
            return;
        }

        $targetSong = null;
        foreach ($this->albums as $album) {
            $targetSong = $album->songs->firstWhere('id', $songId);
            if ($targetSong) {
                break;
            }
        }

        if (!$targetSong) {
            return;
        }

        $this->dispatch('open-spotify-player',
            trackId: $trackId,
            title: $targetSong->title,
            artist: $targetSong->artist_names
        );
    }

    public function isSongPendingSpotify(int $songId): bool
    {
        return isset($this->pendingSpotifyLookups[$songId]);
    }

    public function hasSongBeenResolved(int $songId): bool
    {
        return array_key_exists($songId, $this->resolvedSpotifyIds);
    }

    public function resolveAlbumArtwork(int $albumId): void
    {
        if (array_key_exists($albumId, $this->resolvedAlbumArtwork)) {
            return;
        }

        if (!$this->albums) {
            return;
        }

        $targetAlbum = $this->albums->firstWhere('id', $albumId);

        if (!$targetAlbum) {
            return;
        }

        if ($targetAlbum->cover_art_url) {
            $this->resolvedAlbumArtwork[$albumId] = $targetAlbum->cover_art_url;
            return;
        }

        $artworkUrl = null;

        if ($targetAlbum->number) {
            $scraper = app(FandomScraper::class);
            $fandomUrl = $scraper->getAlbumFandomUrl($targetAlbum->number);
            $artworkUrl = $scraper->scrapeAlbumCover($fandomUrl);
        }

        $this->resolvedAlbumArtwork[$albumId] = $artworkUrl;

        if ($artworkUrl) {
            $targetAlbum->cover_art_url = $artworkUrl;
            $targetAlbum->saveQuietly();
        }
    }

    public function getAlbumArtwork(int $albumId): ?string
    {
        $url = $this->resolvedAlbumArtwork[$albumId] ?? null;

        if ($url && str_contains($url, 'wikia.nocookie.net')) {
            return route('image.proxy', ['url' => base64_encode($url)]);
        }

        return $url;
    }

    public function hasAlbumArtworkBeenResolved(int $albumId): bool
    {
        return array_key_exists($albumId, $this->resolvedAlbumArtwork);
    }

    public function updatedSelectedDate(): void
    {
        if ($this->searched) {
            $this->search();
        }
    }

    public function surpriseMe(): void
    {
        $randomDate = app(MusicLookupService::class)->getRandomValidDate();
        
        if ($randomDate) {
            $this->selectedDate = $randomDate->format('Y-m-d');
            $this->search();
        }
    }

    public function pickEra(string $year): void
    {
        $date = Carbon::create($year, 1, 1);

        if ($this->minAlbumReleaseDate && $date->lt(Carbon::parse($this->minAlbumReleaseDate))) {
            $date = Carbon::parse($this->minAlbumReleaseDate);
        }

        if ($this->maxAlbumReleaseDate && $date->gt(Carbon::parse($this->maxAlbumReleaseDate))) {
            $date = Carbon::parse($this->maxAlbumReleaseDate);
        }

        $this->selectedDate = $date->format('Y-m-d');
        $this->search();
    }

    public function isEraRelevant(string $year): bool
    {
        if (!$this->minAlbumReleaseDate || !$this->maxAlbumReleaseDate) {
            return false;
        }

        $eraYear = (int) $year;
        $minYear = Carbon::parse($this->minAlbumReleaseDate)->year;
        $maxYear = Carbon::parse($this->maxAlbumReleaseDate)->year;

        $eraDecade = (int) (floor($eraYear / 10) * 10);
        $minDecade = (int) (floor($minYear / 10) * 10);
        $maxDecade = (int) (floor($maxYear / 10) * 10);

        return ($eraYear >= $minYear && $eraYear <= $maxYear)
            || ($eraDecade >= $minDecade && $eraDecade <= $maxDecade);
    }
};
?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    <div class="max-w-5xl mx-auto p-6 space-y-12">
        <header class="text-center space-y-6 pt-12 pb-6">
            <div class="inline-flex items-center justify-center p-2 px-4 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 font-medium text-sm mb-4 animate-in fade-in zoom-in duration-700">
                <flux:icon icon="musical-note" class="size-4 mr-2" />
                The Ultimate Music Time Machine
            </div>
            <div class="space-y-2">
                <flux:heading size="xl" level="1" class="text-5xl font-black tracking-tight bg-gradient-to-br from-zinc-900 to-zinc-500 dark:from-white dark:to-zinc-500 bg-clip-text text-transparent">
                    What Did They Call Music?
                </flux:heading>
                <flux:text size="lg" variant="subtle" class="max-w-lg mx-auto">
                    Take a trip down memory lane and discover which hits defined every era of the legendary NOW compilation series.
                </flux:text>
            </div>
        </header>

        <flux:card class="shadow-2xl border-none bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl ring-1 ring-zinc-200 dark:ring-zinc-800">
            <div class="space-y-6">
                <div class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-1 w-full">
                        <flux:field>
                            <flux:label class="text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider text-xs">Pick Your Moment in Time</flux:label>
                            <flux:date-picker
                                wire:model.live="selectedDate"
                                :min="$minAlbumReleaseDate"
                                :max="$maxAlbumReleaseDate"
                                selectable-header
                                with-today
                                clearable
                                class="!bg-transparent border-zinc-200 dark:border-zinc-800 focus:ring-blue-500"
                                :invalid="$errors->has('selectedDate')"
                            />
                            <flux:error name="selectedDate" />
                        </flux:field>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <flux:button variant="primary" wire:click="search" class="grow sm:grow-0 px-8 bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/20 transition-all duration-300">
                            Search
                        </flux:button>
                        <flux:button variant="ghost" wire:click="surpriseMe" tooltip="Random Date" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <flux:icon icon="sparkles" class="size-5 text-amber-500" />
                        </flux:button>
                    </div>
                </div>

                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest mr-2">Quick Jumps:</span>
                        @if($this->isEraRelevant('1983'))
                            <flux:button size="xs" variant="subtle" wire:click="pickEra('1983')" class="rounded-full">80s Origins</flux:button>
                        @endif
                        @if($this->isEraRelevant('1995'))
                            <flux:button size="xs" variant="subtle" wire:click="pickEra('1995')" class="rounded-full">90s Pop</flux:button>
                        @endif
                        @if($this->isEraRelevant('2005'))
                            <flux:button size="xs" variant="subtle" wire:click="pickEra('2005')" class="rounded-full">Y2K Hits</flux:button>
                        @endif
                        @if($this->isEraRelevant('2015'))
                            <flux:button size="xs" variant="subtle" wire:click="pickEra('2015')" class="rounded-full">Modern Eras</flux:button>
                        @endif
                        @if($this->isEraRelevant('2020'))
                            <flux:button size="xs" variant="subtle" wire:click="pickEra('2020')" class="rounded-full">2020s</flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </flux:card>

    @if($errorMessage)
        <flux:callout variant="danger" heading="Couldn’t complete search" text="{{ $errorMessage }}" class="animate-in fade-in slide-in-from-top-2" />
    @endif

    <div wire:loading wire:target="search" class="w-full">
        <flux:card class="space-y-6">
            <div class="flex items-center gap-4">
                <flux:skeleton variant="circle" class="size-16" />
                <div class="flex-1 space-y-2">
                    <flux:skeleton class="h-6 w-1/3" />
                    <flux:skeleton class="h-4 w-1/2" />
                </div>
            </div>
            <flux:skeleton.group animate="shimmer" class="space-y-3">
                <flux:skeleton.line />
                <flux:skeleton.line />
                <flux:skeleton.line class="w-3/4" />
            </flux:skeleton.group>
        </flux:card>
    </div>

    <div wire:loading.remove wire:target="search">
        @php $albums = $this->albums; @endphp
        @if($albums && $albums->count() > 0)
            <div class="space-y-16">
                @foreach($albums as $album)
                    <div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-1000 fill-mode-both" style="animation-delay: {{ $loop->index * 150 }}ms" wire:key="album-{{ $album->id }}">
                        <div class="relative group">
                            <!-- Background Blur Effect -->
                            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-3xl blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200"></div>
                            
                            <flux:card class="relative p-0 overflow-hidden border-none shadow-2xl bg-white dark:bg-zinc-900 rounded-2xl">
                                <div class="grid md:grid-cols-[auto_1fr] items-stretch">
                                    <div class="relative bg-zinc-900 aspect-square md:w-80 w-full overflow-hidden">
                                        @php
                                            $albumArtwork = $this->getAlbumArtwork($album->id);
                                            $artworkResolved = $this->hasAlbumArtworkBeenResolved($album->id);
                                        @endphp
                                        <div class="h-full w-full flex items-center justify-center" @if(!$artworkResolved) wire:intersect.once="resolveAlbumArtwork({{ $album->id }})" @endif>
                                            @if($albumArtwork)
                                                <img src="{{ $albumArtwork }}" alt="{{ $album->name }}" class="h-full w-full object-cover transform transition-transform duration-700 group-hover:scale-105">
                                            @elseif(!$artworkResolved)
                                                <flux:skeleton animate="shimmer" class="h-full w-full bg-zinc-800" />
                                            @else
                                                <div class="flex flex-col items-center gap-4 text-zinc-600">
                                                    <flux:icon icon="musical-note" class="size-20" />
                                                    <span class="text-xs font-bold uppercase tracking-widest">No Cover Art</span>
                                                </div>
                                            @endif
                                        </div>
                                        <!-- Album Type Badge Overlay -->
                                        <div class="absolute top-4 left-4">
                                            <flux:badge size="sm" color="{{ $album->type === 'regular' ? 'blue' : 'amber' }}" class="shadow-lg backdrop-blur-md bg-white/10 text-white border-white/20">
                                                {{ strtoupper($album->type) }}
                                            </flux:badge>
                                        </div>
                                    </div>

                                    <div class="p-8 md:p-10 flex flex-col justify-center space-y-6 bg-gradient-to-br from-white to-zinc-50 dark:from-zinc-900 dark:to-zinc-950">
                                        <div class="space-y-2">
                                            <flux:text size="sm" class="text-blue-600 dark:text-blue-400 font-bold uppercase tracking-[0.2em]">NOW Collection</flux:text>
                                            <flux:heading size="xl" class="text-4xl md:text-5xl font-black tracking-tighter">{{ $album->display_name }}</flux:heading>
                                            <flux:text size="lg" class="text-zinc-500 dark:text-zinc-400 font-medium">{{ $album->name }}</flux:text>
                                        </div>
                                        
                                        <div class="flex flex-wrap gap-4 items-center text-zinc-500 dark:text-zinc-400">
                                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800/50">
                                                <flux:icon icon="calendar" class="size-4" />
                                                <span class="text-sm font-medium">{{ $album->formatted_release_date }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800/50">
                                                <flux:icon icon="list-bullet" class="size-4" />
                                                <span class="text-sm font-medium">{{ $album->songs->count() }} Tracks</span>
                                            </div>
                                        </div>

                                        <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed max-w-xl">
                                            Experience the sound of <span class="text-zinc-900 dark:text-white font-semibold">{{ Carbon::parse($album->release_date)->format('F Y') }}</span>. 
                                            This album captured the zeitgeist with {{ $album->songs->count() }} definitive tracks that defined a generation.
                                        </p>
                                    </div>
                                </div>
                            </flux:card>
                        </div>

                        <div class="px-2">
                            @if($album->songs->count() > 0)
                                <div class="bg-white/50 dark:bg-white/[0.02] rounded-3xl border border-zinc-200 dark:border-white/5 overflow-hidden backdrop-blur-sm">
                                    <flux:table class="!border-none">
                                        <flux:table.columns>
                                            <flux:table.column class="w-12 !text-center">#</flux:table.column>
                                            <flux:table.column class="min-w-[200px]">Track</flux:table.column>
                                            <flux:table.column class="min-w-[150px]">Artist</flux:table.column>
                                            <flux:table.column align="end" class="w-32">Listen</flux:table.column>
                                        </flux:table.columns>

                                        <flux:table.rows>
                                            @foreach($album->songs as $song)
                                                <flux:table.row wire:key="song-{{ $song->id }}" class="group hover:bg-white dark:hover:bg-white/5 transition-colors duration-200">
                                                    <flux:table.cell class="!text-center">
                                                        <span class="text-xs font-bold text-zinc-400 group-hover:text-blue-500 transition-colors">{{ $song->pivot->track_number }}</span>
                                                    </flux:table.cell>
                                                    <flux:table.cell>
                                                        <div class="flex flex-col">
                                                            <span class="font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $song->title }}</span>
                                                            @if($song->original_release_year)
                                                                <span class="text-[10px] uppercase tracking-wider text-zinc-400 font-bold mt-0.5">{{ $song->original_release_year }}</span>
                                                            @endif
                                                        </div>
                                                    </flux:table.cell>
                                                    <flux:table.cell>
                                                        @php
                                                            $artistNames = $song->artist_names;
                                                            $maxLength = 40;
                                                            $isTruncated = Str::length($artistNames) > $maxLength;
                                                        @endphp
                                                        <div class="text-zinc-600 dark:text-zinc-400 font-medium">
                                                            @if($isTruncated)
                                                                <flux:tooltip :content="$artistNames" position="top">
                                                                    <span class="cursor-help decoration-dotted underline-offset-4 decoration-zinc-300 dark:decoration-zinc-700 hover:decoration-blue-400 transition-colors">
                                                                        {{ Str::limit($artistNames, $maxLength) }}
                                                                    </span>
                                                                </flux:tooltip>
                                                            @else
                                                                {{ $artistNames }}
                                                            @endif
                                                        </div>
                                                    </flux:table.cell>
                                                    <flux:table.cell align="end">
                                                        @php
                                                            $spotifyUrl = $this->getSpotifyUrlForSong($song->id);
                                                            $isResolved = $this->hasSongBeenResolved($song->id);
                                                            $hasArtists = $song->primaryArtists->isNotEmpty();
                                                        @endphp
                                                        <div class="flex justify-end items-center gap-2" wire:key="spotify-{{ $song->id }}" @if(!$isResolved && $hasArtists) wire:intersect.once="resolveSpotifyForSong({{ $song->id }})" @endif>
                                                            @if($spotifyUrl)
                                                                <flux:button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    square
                                                                    as="a"
                                                                    href="{{ $spotifyUrl }}"
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                    title="Open on Spotify"
                                                                    class="hover:bg-green-50 dark:hover:bg-green-500/10 hover:text-green-600 transition-all duration-300"
                                                                >
                                                                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.42 1.56-.299.421-1.02.599-1.559.3z"/>
                                                                    </svg>
                                                                </flux:button>
                                                            @elseif(!$isResolved && $hasArtists)
                                                                <div class="size-8 flex items-center justify-center">
                                                                    <svg class="size-4 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                    </svg>
                                                                </div>
                                                            @else
                                                                <span class="text-zinc-300 dark:text-zinc-700 font-bold">—</span>
                                                            @endif

                                                            @if($song->apple_music_url)
                                                                <flux:button variant="ghost" size="sm" square as="a" href="{{ $song->apple_music_url }}" target="_blank" title="Apple Music" class="hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 transition-all duration-300">
                                                                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M23.997 6.124c0-.738-.065-1.47-.24-2.19-.317-1.31-1.062-2.31-2.18-3.043C21.003.517 20.373.285 19.7.164c-.517-.093-1.038-.135-1.564-.15-.04-.001-.08-.004-.12-.004H5.986c-.04 0-.08.003-.12.004-.525.015-1.046.057-1.563.15-.674.121-1.304.353-1.878.727-1.118.733-1.863 1.732-2.18 3.043-.175.72-.24 1.452-.24 2.19v11.751c0 .738.065 1.47.24 2.189.317 1.312 1.062 2.312 2.18 3.044.574.374 1.204.606 1.878.727.517.093 1.038.135 1.563.15.04.001.08.004.12.004h12.028c.04 0 .08-.003.12-.004.526-.015 1.047-.057 1.564-.15.673-.121 1.303-.353 1.877-.727 1.118-.732 1.863-1.732 2.18-3.044.175-.719.24-1.451.24-2.189V6.124zM11.997 19.997c-4.14 0-7.5-3.357-7.5-7.497s3.36-7.497 7.5-7.497 7.5 3.357 7.5 7.497-3.36 7.497-7.5 7.497zm0-13.497c-3.309 0-6 2.691-6 6s2.691 6 6 6 6-2.691 6-6-2.691-6-6-6z"/>
                                                                    </svg>
                                                                </flux:button>
                                                            @endif
                                                        </div>
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </div>
                            @else
                                <div class="text-center py-20 bg-zinc-50 dark:bg-zinc-800/20 rounded-3xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
                                    <flux:icon icon="musical-note" class="size-12 mx-auto text-zinc-300 dark:text-zinc-700 mb-4" />
                                    <flux:text variant="subtle">No tracks found for this edition.</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($searched && !$errorMessage)
            <div class="text-center py-20 space-y-6 animate-in fade-in zoom-in duration-700">
                <div class="inline-flex items-center justify-center size-20 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 mb-4">
                    <flux:icon icon="magnifying-glass" class="size-10" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-2xl font-bold">No albums found</flux:heading>
                    <flux:text variant="subtle" class="max-w-xs mx-auto text-base">We couldn't find any NOW albums for this specific date. Try exploring another year!</flux:text>
                </div>
                <flux:button variant="subtle" wire:click="surpriseMe" class="mt-4">
                    Try a random date
                </flux:button>
            </div>
        @else
            <!-- Initial State -->
            <div class="space-y-12 animate-in fade-in slide-in-from-bottom-8 duration-1000 fill-mode-both">
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 pt-12">
                    <div class="p-8 rounded-3xl bg-gradient-to-br from-blue-500/5 to-indigo-500/5 border border-blue-500/10 space-y-4 group hover:border-blue-500/30 transition-colors">
                        <flux:icon icon="sparkles" class="size-8 text-blue-500 group-hover:scale-110 transition-transform" />
                        <flux:heading size="sm" class="font-bold text-lg">Instant Nostalgia</flux:heading>
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Search any date to see what everyone was listening to. From cassette tapes to digital streaming.</flux:text>
                    </div>
                    <div class="p-8 rounded-3xl bg-gradient-to-br from-purple-500/5 to-pink-500/5 border border-purple-500/10 space-y-4 group hover:border-purple-500/30 transition-colors">
                        <flux:icon icon="musical-note" class="size-8 text-purple-500 group-hover:scale-110 transition-transform" />
                        <flux:heading size="sm" class="font-bold text-lg">Direct Listen</flux:heading>
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Connect directly to Spotify and Apple Music to listen to the tracks that defined your favorite years.</flux:text>
                    </div>
                    <div class="p-8 rounded-3xl bg-gradient-to-br from-amber-500/5 to-orange-500/5 border border-amber-500/10 space-y-4 group hover:border-amber-500/30 transition-colors sm:col-span-2 lg:col-span-1">
                        <flux:icon icon="clock" class="size-8 text-amber-500 group-hover:scale-110 transition-transform" />
                        <flux:heading size="sm" class="font-bold text-lg">40 Years of Music</flux:heading>
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">Explore four decades of music history across hundreds of legendary NOW compilation albums.</flux:text>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="relative overflow-hidden rounded-[3rem] bg-zinc-900 text-white p-12 shadow-2xl">
                    <div class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 size-96 bg-blue-600/20 blur-[100px] rounded-full"></div>
                    <div class="absolute bottom-0 left-0 translate-y-1/2 -translate-x-1/2 size-96 bg-purple-600/20 blur-[100px] rounded-full"></div>
                    
                    @php $stats = $this->stats; @endphp
                    <dl class="relative z-10 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                        <div class="space-y-1">
                            <dt class="text-zinc-400 uppercase tracking-widest text-[10px] font-black">Total Albums</dt>
                            <dd class="text-4xl font-black tabular-nums">{{ number_format($stats['total_albums'] ?? 0) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-zinc-400 uppercase tracking-widest text-[10px] font-black">Songs Indexed</dt>
                            <dd class="text-4xl font-black tabular-nums">{{ number_format($stats['total_songs'] ?? 0) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-zinc-400 uppercase tracking-widest text-[10px] font-black">Era Started</dt>
                            <dd class="text-4xl font-black tabular-nums">{{ ($stats['date_range']['first'] ?? null)?->year ?? '—' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-zinc-400 uppercase tracking-widest text-[10px] font-black">Latest Hit</dt>
                            <dd class="text-4xl font-black tabular-nums">{{ ($stats['date_range']['latest'] ?? null)?->year ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @endif
    </div>
</div>
</div>
