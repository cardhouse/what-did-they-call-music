<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Album;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class ManageAlbumAffiliates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'albums:affiliate
        {--list : List all albums and affiliate links}
        {--missing : List albums missing affiliate links}
        {--set= : Set or update an affiliate link}
        {--clear : Clear an affiliate link}
        {--album= : Album number for updates}
        {--album-id= : Album ID for un-numbered editions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage album affiliate links';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $actions = array_filter([
            'list' => (bool) $this->option('list'),
            'missing' => (bool) $this->option('missing'),
            'set' => $this->option('set') !== null,
            'clear' => (bool) $this->option('clear'),
        ]);

        if (count($actions) !== 1) {
            $this->error('Choose exactly one action: --list, --missing, --set, or --clear.');

            return Command::FAILURE;
        }

        if ($this->option('list')) {
            return $this->renderList(Album::query());
        }

        if ($this->option('missing')) {
            return $this->renderList(
                Album::query()->where(function (Builder $query): void {
                    $query->whereNull('affiliate_url')
                        ->orWhere('affiliate_url', '');
                })
            );
        }

        if ($this->option('set') !== null) {
            return $this->setAffiliateUrl();
        }

        return $this->clearAffiliateUrl();
    }

    protected function renderList(Builder $query): int
    {
        $albums = $query
            ->orderBy('release_date')
            ->orderBy('type')
            ->orderBy('number')
            ->get();

        if ($albums->isEmpty()) {
            $this->info('No albums found.');

            return Command::SUCCESS;
        }

        $rows = $albums->map(fn (Album $album): array => [
            'id' => $album->id,
            'number' => $album->number ?? '—',
            'type' => $album->type,
            'display' => $album->display_name,
            'release_date' => $album->release_date?->format('Y-m-d') ?? '—',
            'affiliate_url' => $album->affiliate_url ?: '—',
        ]);

        $this->table(
            ['ID', 'Number', 'Type', 'Album', 'Release Date', 'Affiliate URL'],
            $rows->all()
        );

        return Command::SUCCESS;
    }

    protected function setAffiliateUrl(): int
    {
        $url = trim((string) $this->option('set'));

        if ($url === '') {
            $this->error('Provide a non-empty URL with --set.');

            return Command::FAILURE;
        }

        if (! $this->isValidUrl($url)) {
            $this->error('The affiliate URL must be a valid URL.');

            return Command::FAILURE;
        }

        $album = $this->resolveTargetAlbum();

        if (! $album) {
            return Command::FAILURE;
        }

        $album->affiliate_url = $url;
        $album->save();

        $this->info("Affiliate URL updated for {$album->display_name}.");

        return Command::SUCCESS;
    }

    protected function clearAffiliateUrl(): int
    {
        $album = $this->resolveTargetAlbum();

        if (! $album) {
            return Command::FAILURE;
        }

        $album->affiliate_url = null;
        $album->save();

        $this->info("Affiliate URL cleared for {$album->display_name}.");

        return Command::SUCCESS;
    }

    protected function resolveTargetAlbum(): ?Album
    {
        $albumId = $this->option('album-id');
        $albumNumber = $this->option('album');

        if ($albumId) {
            $album = Album::query()->find($albumId);

            if (! $album) {
                $this->error('No album found for the provided --album-id.');

                return null;
            }

            return $album;
        }

        if ($albumNumber === null) {
            $this->error('Provide --album for numbered editions or --album-id for specials.');

            return null;
        }

        if (! is_numeric($albumNumber)) {
            $this->error('The --album value must be a numeric album number.');

            return null;
        }

        $matches = Album::query()
            ->where('number', (int) $albumNumber)
            ->orderBy('type')
            ->get();

        if ($matches->isEmpty()) {
            $this->error('No album found for that album number.');

            return null;
        }

        if ($matches->count() > 1) {
            $this->error('Multiple albums share that number. Use --album-id instead.');

            $this->table(
                ['ID', 'Number', 'Type', 'Album', 'Release Date'],
                $matches->map(fn (Album $album): array => [
                    'id' => $album->id,
                    'number' => $album->number ?? '—',
                    'type' => $album->type,
                    'display' => $album->display_name,
                    'release_date' => $album->release_date?->format('Y-m-d') ?? '—',
                ])->all()
            );

            return null;
        }

        return $matches->first();
    }

    protected function isValidUrl(string $url): bool
    {
        $validator = Validator::make(['url' => $url], [
            'url' => ['required', 'url'],
        ]);

        return ! $validator->fails();
    }
}
