<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WikipediaScraper
{
    private Client $client;

    private const BASE_URL = 'https://en.wikipedia.org';

    private const USER_AGENT = 'WhatDidTheyCallMusic/1.0 (Educational Project)';

    /**
     * US NOW albums data - manually curated list with Wikipedia URLs
     * This is more reliable than scraping the discography page
     *
     * @var array<int, array{number: int, name: string, release_date: string, type: string, wikipedia_url: string|null}>
     */
    private const US_ALBUMS = [
        ['number' => 1, 'name' => "Now That's What I Call Music!", 'release_date' => '1998-11-10', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_(original_U.S._album)'],
        ['number' => 2, 'name' => "Now That's What I Call Music! 2", 'release_date' => '1999-04-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_2_(American_series)'],
        ['number' => 3, 'name' => "Now That's What I Call Music! 3", 'release_date' => '1999-08-31', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_3_(American_series)'],
        ['number' => 4, 'name' => "Now That's What I Call Music! 4", 'release_date' => '2000-05-02', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_4_(American_series)'],
        ['number' => 5, 'name' => "Now That's What I Call Music! 5", 'release_date' => '2000-11-14', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_5_(American_series)'],
        ['number' => 6, 'name' => "Now That's What I Call Music! 6", 'release_date' => '2001-03-27', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_6_(American_series)'],
        ['number' => 7, 'name' => "Now That's What I Call Music! 7", 'release_date' => '2001-07-17', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_7_(American_series)'],
        ['number' => 8, 'name' => "Now That's What I Call Music! 8", 'release_date' => '2001-11-13', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_8_(American_series)'],
        ['number' => 9, 'name' => "Now That's What I Call Music! 9", 'release_date' => '2002-03-26', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_9_(American_series)'],
        ['number' => 10, 'name' => "Now That's What I Call Music! 10", 'release_date' => '2002-07-23', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_10_(American_series)'],
        ['number' => 11, 'name' => "Now That's What I Call Music! 11", 'release_date' => '2002-11-12', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_11_(American_series)'],
        ['number' => 12, 'name' => "Now That's What I Call Music! 12", 'release_date' => '2003-03-25', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_12_(American_series)'],
        ['number' => 13, 'name' => "Now That's What I Call Music! 13", 'release_date' => '2003-07-22', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_13_(U.S._series)'],
        ['number' => 14, 'name' => "Now That's What I Call Music! 14", 'release_date' => '2003-11-04', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_14_(American_series)'],
        ['number' => 15, 'name' => "Now That's What I Call Music! 15", 'release_date' => '2004-03-23', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_15_(American_series)'],
        ['number' => 16, 'name' => "Now That's What I Call Music! 16", 'release_date' => '2004-07-20', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_16_(American_series)'],
        ['number' => 17, 'name' => "Now That's What I Call Music! 17", 'release_date' => '2004-11-09', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_17_(American_series)'],
        ['number' => 18, 'name' => "Now That's What I Call Music! 18", 'release_date' => '2005-03-22', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_18_(American_series)'],
        ['number' => 19, 'name' => "Now That's What I Call Music! 19", 'release_date' => '2005-07-19', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_19_(American_series)'],
        ['number' => 20, 'name' => "Now That's What I Call Music! 20", 'release_date' => '2005-11-08', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_20_(American_series)'],
        ['number' => 21, 'name' => "Now That's What I Call Music! 21", 'release_date' => '2006-03-21', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_21_(American_series)'],
        ['number' => 22, 'name' => "Now That's What I Call Music! 22", 'release_date' => '2006-07-18', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_22_(American_series)'],
        ['number' => 23, 'name' => "Now That's What I Call Music! 23", 'release_date' => '2006-11-07', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_23_(American_series)'],
        ['number' => 24, 'name' => "Now That's What I Call Music! 24", 'release_date' => '2007-03-20', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_24_(American_series)'],
        ['number' => 25, 'name' => "Now That's What I Call Music! 25", 'release_date' => '2007-07-17', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_25_(American_series)'],
        ['number' => 26, 'name' => "Now That's What I Call Music! 26", 'release_date' => '2007-11-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_26_(American_series)'],
        ['number' => 27, 'name' => "Now That's What I Call Music! 27", 'release_date' => '2008-03-18', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_27_(American_series)'],
        ['number' => 28, 'name' => "Now That's What I Call Music! 28", 'release_date' => '2008-07-15', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_28_(American_series)'],
        ['number' => 29, 'name' => "Now That's What I Call Music! 29", 'release_date' => '2008-11-11', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_29_(American_series)'],
        ['number' => 30, 'name' => "Now That's What I Call Music! 30", 'release_date' => '2009-03-17', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_30_(American_series)'],
        ['number' => 31, 'name' => "Now That's What I Call Music! 31", 'release_date' => '2009-07-14', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_31_(American_series)'],
        ['number' => 32, 'name' => "Now That's What I Call Music! 32", 'release_date' => '2009-11-10', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_32_(American_series)'],
        ['number' => 33, 'name' => "Now That's What I Call Music! 33", 'release_date' => '2010-03-16', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_33_(American_series)'],
        ['number' => 34, 'name' => "Now That's What I Call Music! 34", 'release_date' => '2010-07-13', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_34_(American_series)'],
        ['number' => 35, 'name' => "Now That's What I Call Music! 35", 'release_date' => '2010-11-09', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_35_(American_series)'],
        ['number' => 36, 'name' => "Now That's What I Call Music! 36", 'release_date' => '2010-11-09', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_36_(American_series)'],
        ['number' => 37, 'name' => "Now That's What I Call Music! 37", 'release_date' => '2011-03-22', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_37_(American_series)'],
        ['number' => 38, 'name' => "Now That's What I Call Music! 38", 'release_date' => '2011-07-12', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_38_(American_series)'],
        ['number' => 39, 'name' => "Now That's What I Call Music! 39", 'release_date' => '2011-11-08', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_39_(American_series)'],
        ['number' => 40, 'name' => "Now That's What I Call Music! 40", 'release_date' => '2011-11-08', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_40_(American_series)'],
        ['number' => 41, 'name' => "Now That's What I Call Music! 41", 'release_date' => '2012-03-27', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_41_(American_series)'],
        ['number' => 42, 'name' => "Now That's What I Call Music! 42", 'release_date' => '2012-07-10', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_42_(American_series)'],
        ['number' => 43, 'name' => "Now That's What I Call Music! 43", 'release_date' => '2012-08-07', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_43_(American_series)'],
        ['number' => 44, 'name' => "Now That's What I Call Music! 44", 'release_date' => '2012-11-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_44_(American_series)'],
        ['number' => 45, 'name' => "Now That's What I Call Music! 45", 'release_date' => '2013-02-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_45_(American_series)'],
        ['number' => 46, 'name' => "Now That's What I Call Music! 46", 'release_date' => '2013-05-07', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_46_(American_series)'],
        ['number' => 47, 'name' => "Now That's What I Call Music! 47", 'release_date' => '2013-08-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_47_(American_series)'],
        ['number' => 48, 'name' => "Now That's What I Call Music! 48", 'release_date' => '2013-11-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_48_(American_series)'],
        ['number' => 49, 'name' => "Now That's What I Call Music! 49", 'release_date' => '2014-02-04', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_49_(American_series)'],
        ['number' => 50, 'name' => "Now That's What I Call Music! 50", 'release_date' => '2014-05-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_50_(American_series)'],
        ['number' => 51, 'name' => "Now That's What I Call Music! 51", 'release_date' => '2014-08-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_51_(American_series)'],
        ['number' => 52, 'name' => "Now That's What I Call Music! 52", 'release_date' => '2014-11-04', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_52_(American_series)'],
        ['number' => 53, 'name' => "Now That's What I Call Music! 53", 'release_date' => '2015-02-03', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_53_(American_series)'],
        ['number' => 54, 'name' => "Now That's What I Call Music! 54", 'release_date' => '2015-05-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_54_(American_series)'],
        ['number' => 55, 'name' => "Now That's What I Call Music! 55", 'release_date' => '2015-08-07', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_55_(American_series)'],
        ['number' => 56, 'name' => "Now That's What I Call Music! 56", 'release_date' => '2015-11-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_56_(American_series)'],
        ['number' => 57, 'name' => "Now That's What I Call Music! 57", 'release_date' => '2016-02-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_57_(American_series)'],
        ['number' => 58, 'name' => "Now That's What I Call Music! 58", 'release_date' => '2016-05-06', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_58_(American_series)'],
        ['number' => 59, 'name' => "Now That's What I Call Music! 59", 'release_date' => '2016-08-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_59_(American_series)'],
        ['number' => 60, 'name' => "Now That's What I Call Music! 60", 'release_date' => '2016-11-04', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_60_(American_series)'],
        ['number' => 61, 'name' => "Now That's What I Call Music! 61", 'release_date' => '2017-02-03', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_61_(American_series)'],
        ['number' => 62, 'name' => "Now That's What I Call Music! 62", 'release_date' => '2017-05-05', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_62_(American_series)'],
        ['number' => 63, 'name' => "Now That's What I Call Music! 63", 'release_date' => '2017-08-04', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_63_(American_series)'],
        ['number' => 64, 'name' => "Now That's What I Call Music! 64", 'release_date' => '2017-11-03', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_64_(American_series)'],
        ['number' => 65, 'name' => "Now That's What I Call Music! 65", 'release_date' => '2018-02-02', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_65_(American_series)'],
        ['number' => 66, 'name' => "Now That's What I Call Music! 66", 'release_date' => '2018-05-04', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_66_(American_series)'],
        ['number' => 67, 'name' => "Now That's What I Call Music! 67", 'release_date' => '2018-08-03', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_67_(American_series)'],
        ['number' => 68, 'name' => "Now That's What I Call Music! 68", 'release_date' => '2018-11-02', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_68_(American_series)'],
        ['number' => 69, 'name' => "Now That's What I Call Music! 69", 'release_date' => '2019-02-01', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_69_(American_series)'],
        ['number' => 70, 'name' => "Now That's What I Call Music! 70", 'release_date' => '2019-05-03', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_70_(American_series)'],
        ['number' => 71, 'name' => "Now That's What I Call Music! 71", 'release_date' => '2019-08-02', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_71_(American_series)'],
        ['number' => 72, 'name' => "Now That's What I Call Music! 72", 'release_date' => '2019-11-01', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_72_(American_series)'],
        ['number' => 73, 'name' => "Now That's What I Call Music! 73", 'release_date' => '2020-01-31', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_73_(American_series)'],
        ['number' => 74, 'name' => "Now That's What I Call Music! 74", 'release_date' => '2020-05-01', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_74_(American_series)'],
        ['number' => 75, 'name' => "Now That's What I Call Music! 75", 'release_date' => '2020-07-31', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_75_(American_series)'],
        ['number' => 76, 'name' => "Now That's What I Call Music! 76", 'release_date' => '2020-10-30', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_76_(American_series)'],
        ['number' => 77, 'name' => "Now That's What I Call Music! 77", 'release_date' => '2021-01-29', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_77_(American_series)'],
        ['number' => 78, 'name' => "Now That's What I Call Music! 78", 'release_date' => '2021-04-30', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_78_(American_series)'],
        ['number' => 79, 'name' => "Now That's What I Call Music! 79", 'release_date' => '2021-07-30', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_79_(American_series)'],
        ['number' => 80, 'name' => "Now That's What I Call Music! 80", 'release_date' => '2021-10-29', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_80_(American_series)'],
        ['number' => 81, 'name' => "Now That's What I Call Music! 81", 'release_date' => '2022-01-28', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_81_(American_series)'],
        ['number' => 82, 'name' => "Now That's What I Call Music! 82", 'release_date' => '2022-04-29', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_82_(American_series)'],
        ['number' => 83, 'name' => "Now That's What I Call Music! 83", 'release_date' => '2022-07-29', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_83_(American_series)'],
        ['number' => 84, 'name' => "Now That's What I Call Music! 84", 'release_date' => '2022-10-28', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_84_(American_series)'],
        ['number' => 85, 'name' => "Now That's What I Call Music! 85", 'release_date' => '2023-01-27', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_85_(American_series)'],
        ['number' => 86, 'name' => "Now That's What I Call Music! 86", 'release_date' => '2023-04-28', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_86_(American_series)'],
        ['number' => 87, 'name' => "Now That's What I Call Music! 87", 'release_date' => '2023-07-28', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_87_(American_series)'],
        ['number' => 88, 'name' => "Now That's What I Call Music! 88", 'release_date' => '2023-10-27', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_88_(American_series)'],
        ['number' => 89, 'name' => "Now That's What I Call Music! 89", 'release_date' => '2024-01-26', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_89_(American_series)'],
        ['number' => 90, 'name' => "Now That's What I Call Music! 90", 'release_date' => '2024-05-03', 'type' => 'regular', 'wikipedia_url' => '/wiki/Now_That%27s_What_I_Call_Music!_90_(American_series)'],
    ];

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
            ],
        ]);
    }

    /**
     * Get the list of all US NOW albums.
     *
     * @return Collection<int, array{number: int, name: string, release_date: string, type: string, wikipedia_url: string|null}>
     */
    public function getAlbumList(): Collection
    {
        return collect(self::US_ALBUMS);
    }

    /**
     * Scrape track listing from a Wikipedia album page.
     *
     * @return array<int, array{track_number: int, title: string, artist: string}>
     */
    public function scrapeTrackListing(string $wikipediaUrl): array
    {
        try {
            $response = $this->client->get($wikipediaUrl);
            $html = (string) $response->getBody();

            return $this->parseTrackListing($html);
        } catch (GuzzleException $e) {
            Log::warning("Failed to fetch Wikipedia page: {$wikipediaUrl}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse track listing from HTML content.
     *
     * @return array<int, array{track_number: int, title: string, artist: string}>
     */
    private function parseTrackListing(string $html): array
    {
        $tracks = [];
        $trackOffset = 0;

        // Wikipedia NOW album pages use a specific tracklist table format
        // The table has class "tracklist" and contains rows with:
        // - Track number in first <td>
        // - Title in second <td> (often with quotes and links)
        // - Artist in third <td> (often with links)
        // - Length in fourth <td>

        // Find all tracklist tables (some pages have multiple)
        if (preg_match_all('/<table[^>]*class="[^"]*tracklist[^"]*"[^>]*>(.*?)<\/table>/si', $html, $tableMatches)) {
            foreach ($tableMatches[1] as $tableHtml) {
                $tableTracks = $this->parseTracklistTable($tableHtml, $trackOffset);
                $tracks = array_merge($tracks, $tableTracks);
                if (! empty($tableTracks)) {
                    $trackOffset = max(array_column($tableTracks, 'track_number'));
                }
            }
        }

        // If no tracklist table found, try generic wikitable after Track_listing heading
        if (empty($tracks)) {
            $tracks = $this->parseGenericTrackTable($html);
        }

        return $tracks;
    }

    /**
     * Parse a Wikipedia tracklist table.
     * These tables have a specific structure:
     * - Track number in <th scope="row"> (e.g., "1.")
     * - Title in first <td> (with quotes and links)
     * - Artist in second <td> (with links)
     * - Length in third <td>
     *
     * @return array<int, array{track_number: int, title: string, artist: string}>
     */
    private function parseTracklistTable(string $tableHtml, int $trackOffset = 0): array
    {
        $tracks = [];

        // Match table rows
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rows);

        foreach ($rows[1] as $row) {
            // Skip header rows (contain <th> with scope="col")
            if (preg_match('/<th[^>]*scope="col"[^>]*>/i', $row)) {
                continue;
            }

            // Extract track number from <th scope="row"> element
            $trackNum = null;
            if (preg_match('/<th[^>]*scope="row"[^>]*>(.*?)<\/th>/si', $row, $thMatch)) {
                // Track number is like "1." - extract just the number
                $trackNumText = $this->cleanText($thMatch[1]);
                $trackNumText = preg_replace('/[^0-9]/', '', $trackNumText);
                if (is_numeric($trackNumText)) {
                    $trackNum = (int) $trackNumText;
                }
            }

            // Extract all <td> cells
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells);

            // We need at least 2 cells (title and artist) and a valid track number
            if ($trackNum && count($cells[1]) >= 2) {
                $title = $this->cleanText($cells[1][0]);
                $artist = $this->cleanText($cells[1][1]);

                // Clean up title - remove surrounding quotes
                $title = preg_replace('/^["\'""]|["\'""]$/u', '', $title);
                $title = trim($title);

                if ($title && $artist) {
                    $tracks[] = [
                        'track_number' => $trackNum + $trackOffset,
                        'title' => $title,
                        'artist' => $artist,
                    ];
                }
            }
        }

        return $tracks;
    }

    /**
     * Parse generic track table from HTML (fallback method).
     *
     * @return array<int, array{track_number: int, title: string, artist: string}>
     */
    private function parseGenericTrackTable(string $html): array
    {
        $tracks = [];

        // Look for wikitable after Track_listing section
        if (! preg_match('/id="Track_listing".*?<table[^>]*class="[^"]*wikitable[^"]*"[^>]*>(.*?)<\/table>/si', $html, $match)) {
            return [];
        }

        $tableHtml = $match[1];

        // Parse rows
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rows);

        $trackNumber = 0;
        foreach ($rows[1] as $row) {
            // Skip header rows
            if (preg_match('/<th[^>]*>/i', $row)) {
                continue;
            }

            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells);

            if (count($cells[1]) >= 2) {
                $trackNumber++;

                // Determine which cells contain what based on content
                $cellContents = array_map(fn ($c) => $this->cleanText($c), $cells[1]);

                // If first cell is numeric, it's the track number
                if (isset($cellContents[0]) && is_numeric($cellContents[0])) {
                    $trackNumber = (int) $cellContents[0];
                    array_shift($cellContents);
                }

                $title = $cellContents[0] ?? '';
                $artist = $cellContents[1] ?? '';

                // Clean up title (remove quotes)
                $title = preg_replace('/^["\'""]|["\'""]$/u', '', $title);
                $title = trim($title);

                if ($title && $artist) {
                    $tracks[] = [
                        'track_number' => $trackNumber,
                        'title' => $title,
                        'artist' => $artist,
                    ];
                }
            }
        }

        return $tracks;
    }

    /**
     * Clean HTML text, removing tags and normalizing whitespace.
     */
    private function cleanText(string $html): string
    {
        // Remove links but keep text
        $text = preg_replace('/<a[^>]*>([^<]*)<\/a>/i', '$1', $html);

        // Remove all other HTML tags
        $text = strip_tags($text ?? '');

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    /**
     * Import all albums and their tracks into the database.
     */
    public function importAllAlbums(bool $dryRun = false, ?callable $progressCallback = null): array
    {
        $stats = [
            'albums_processed' => 0,
            'albums_created' => 0,
            'songs_created' => 0,
            'artists_created' => 0,
            'errors' => [],
        ];

        $albums = $this->getAlbumList();

        foreach ($albums as $albumData) {
            $stats['albums_processed']++;

            if ($progressCallback) {
                $progressCallback("Processing: {$albumData['name']}");
            }

            if ($dryRun) {
                if ($progressCallback) {
                    $progressCallback("  [DRY RUN] Would create album #{$albumData['number']}");
                }

                continue;
            }

            try {
                // Create or update album
                $album = Album::updateOrCreate(
                    [
                        'number' => $albumData['number'],
                        'type' => $albumData['type'],
                    ],
                    [
                        'name' => $albumData['name'],
                        'release_date' => $albumData['release_date'],
                    ]
                );

                if ($album->wasRecentlyCreated) {
                    $stats['albums_created']++;
                }

                // Scrape and import tracks if we have a Wikipedia URL
                if ($albumData['wikipedia_url']) {
                    $tracks = $this->scrapeTrackListing($albumData['wikipedia_url']);

                    if ($progressCallback) {
                        $progressCallback("  Found " . count($tracks) . ' tracks');
                    }

                    foreach ($tracks as $trackData) {
                        // Find or create artist
                        $artist = Artist::firstOrCreate(
                            ['name' => $trackData['artist']]
                        );

                        if ($artist->wasRecentlyCreated) {
                            $stats['artists_created']++;
                        }

                        // Find or create song
                        $song = Song::firstOrCreate(
                            ['title' => $trackData['title']]
                        );

                        if ($song->wasRecentlyCreated) {
                            $stats['songs_created']++;
                        }

                        // Attach song to album if not already attached
                        if (! $album->songs()->where('song_id', $song->id)->exists()) {
                            $album->songs()->attach($song->id, [
                                'track_number' => $trackData['track_number'],
                            ]);
                        }

                        // Attach artist to song if not already attached
                        if (! $song->artists()->where('artist_id', $artist->id)->exists()) {
                            $song->artists()->attach($artist->id, [
                                'is_primary' => true,
                            ]);
                        }
                    }

                    // Be nice to Wikipedia - add a small delay between requests
                    usleep(500000); // 0.5 seconds
                }
            } catch (\Exception $e) {
                $stats['errors'][] = "Album #{$albumData['number']}: " . $e->getMessage();
                Log::error("Failed to import album", [
                    'album' => $albumData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
