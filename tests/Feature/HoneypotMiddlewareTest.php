<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\call;
use function Pest\Laravel\get;

beforeEach(function () {
    config()->set('honeypot.enabled', true);
    config()->set('honeypot.tarpit_enabled', false);
    config()->set('honeypot.handle_acme', true);
    config()->set('honeypot.blocklist_ttl_seconds', 3600);

    Route::get('/_honeypot-test/legitimate', fn () => response('legitimate ok', 200));

    Log::shouldReceive('channel')->with('security')->andReturnSelf()->byDefault();
    Log::shouldReceive('warning')->byDefault();
    Log::shouldReceive('info')->byDefault();
    Log::shouldReceive('error')->byDefault();
});

test('legitimate routes pass through untouched', function () {
    $response = get('/_honeypot-test/legitimate');

    $response->assertOk();
    expect($response->getContent())->toBe('legitimate ok');
});

test('health check path is exempt from honeypot', function () {
    $response = get('/up');

    $response->assertOk();
});

test('legitimate routes complete fast with tarpit enabled', function () {
    config()->set('honeypot.tarpit_enabled', true);
    config()->set('honeypot.tarpit_min_seconds', 30);
    config()->set('honeypot.tarpit_max_seconds', 45);

    $start = microtime(true);
    get('/_honeypot-test/legitimate')->assertOk();
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(2.0);
});

test('wp-login.php triggers a WordPress login decoy', function () {
    $response = get('/wp-login.php');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($response->getContent())
        ->toContain('<title>Log In &lsaquo; WordPress</title>')
        ->toContain('loginform');
});

test('wp-admin path triggers WordPress decoy', function () {
    get('/wp-admin')->assertOk();
    get('/wp-admin/users.php')->assertOk();
});

test('xmlrpc.php POST returns an XML-RPC listMethods response', function () {
    $response = call('POST', '/xmlrpc.php');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    expect($response->getContent())
        ->toContain('pingback.ping')
        ->toContain('wp.getUsers')
        ->toContain('<methodResponse>');
});

test('wp-json users endpoint returns fake admin users', function () {
    $response = get('/wp-json/wp/v2/users');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');

    $data = $response->json();
    expect($data)->toBeArray()->not->toBeEmpty();
    expect($data[0])->toHaveKeys(['id', 'name', 'slug', 'avatar_urls']);
});

test('wp-config.php returns poisoned config', function () {
    $response = get('/wp-config.php');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('DB_NAME')
        ->toContain('DB_HOST')
        ->toMatch('/10\.\d+\.\d+\.\d+/');
});

test('readme.html advertises an old WordPress version', function () {
    $response = get('/readme.html');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($response->getContent())->toMatch('/Version (5\.4|5\.3|5\.2|4\.9)/');
});

test('.env returns poisoned Laravel env with fake AWS credentials', function () {
    $response = get('/.env');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())
        ->toContain('APP_KEY=base64:')
        ->toMatch('/AWS_ACCESS_KEY_ID=AKIA[A-Z2-7]{16}/');
});

test('.git/config returns fake git config', function () {
    $response = get('/.git/config');

    $response->assertOk();
    expect($response->getContent())->toContain('[remote "origin"]');
});

test('.aws/credentials returns fake AWS credentials', function () {
    $response = get('/.aws/credentials');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('[default]')
        ->toMatch('/aws_access_key_id = AKIA[A-Z2-7]{16}/');
});

test('phpmyadmin triggers admin decoy', function () {
    $response = get('/phpmyadmin/');

    $response->assertOk();
    expect($response->getContent())->toContain('phpMyAdmin');
});

test('adminer.php triggers admin decoy', function () {
    $response = get('/adminer.php');

    $response->assertOk();
    expect($response->getContent())->toContain('Adminer');
});

test('shell paths return fake compromised shell', function () {
    $response = get('/c99.php');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('C99Shell')
        ->toContain('Already compromised');
});

test('acme-challenge returns a correctly formatted key authorization', function () {
    $response = get('/.well-known/acme-challenge/sometoken');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toMatch('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/');
});

test('acme bypass config flag lets acme requests through', function () {
    config()->set('honeypot.handle_acme', false);

    $response = get('/.well-known/acme-challenge/sometoken');

    $response->assertNotFound();
});

test('known attack path blocklists the IP', function () {
    $ip = '203.0.113.42';

    call('GET', '/wp-login.php', server: ['REMOTE_ADDR' => $ip])->assertOk();

    expect(Cache::has('honeypot:blocklist:'.$ip))->toBeTrue();
});

test('blocklisted IP is tarpitted on legitimate paths', function () {
    $ip = '203.0.113.43';
    Cache::put('honeypot:blocklist:'.$ip, true, 3600);

    $response = call('GET', '/', server: ['REMOTE_ADDR' => $ip]);

    $response->assertOk();
    expect($response->getContent())->toContain('<title>Log In &lsaquo; WordPress</title>');
});

test('blocklisted IP is tarpitted on arbitrary paths', function () {
    $ip = '203.0.113.44';
    Cache::put('honeypot:blocklist:'.$ip, true, 3600);

    $response = call('GET', '/some/random/path', server: ['REMOTE_ADDR' => $ip]);

    $response->assertOk();
    expect($response->getContent())->toContain('<title>Log In &lsaquo; WordPress</title>');
});

test('concurrent tarpit cap prevents sleeping above the limit', function () {
    config()->set('honeypot.tarpit_enabled', true);
    config()->set('honeypot.tarpit_min_seconds', 30);
    config()->set('honeypot.tarpit_max_seconds', 30);
    config()->set('honeypot.max_concurrent_tarpits', 10);

    Cache::put('honeypot:concurrent', 10);

    $start = microtime(true);
    $response = get('/wp-login.php');
    $duration = microtime(true) - $start;

    $response->assertOk();
    expect($duration)->toBeLessThan(2.0);
});

test('tarpit disabled flag skips the delay', function () {
    config()->set('honeypot.tarpit_enabled', false);
    config()->set('honeypot.tarpit_min_seconds', 30);
    config()->set('honeypot.tarpit_max_seconds', 45);

    $start = microtime(true);
    get('/wp-login.php')->assertOk();
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(2.0);
});

test('honeypot master switch disables middleware entirely', function () {
    config()->set('honeypot.enabled', false);

    get('/wp-login.php')->assertNotFound();
});

test('exempt user agents bypass the honeypot', function () {
    $response = call(
        'GET',
        '/wp-login.php',
        server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']
    );

    $response->assertNotFound();
});

test('wp-cron.php returns empty 200', function () {
    $response = get('/wp-cron.php');

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

test('phpinfo.php returns fake PHP info with old PHP version', function () {
    $response = get('/phpinfo.php');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($response->getContent())->toMatch('/PHP Version 7\.2\./');
});

test('revslider plugin path triggers WordPress decoy', function () {
    $response = get('/wp-content/plugins/revslider/release_log.txt');

    $response->assertOk();
    expect($response->getContent())->toContain('revslider');
});
