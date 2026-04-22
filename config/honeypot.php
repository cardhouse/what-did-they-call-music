<?php

declare(strict_types=1);

use App\Http\Middleware\Decoys\AcmeDecoy;
use App\Http\Middleware\Decoys\AdminDecoy;
use App\Http\Middleware\Decoys\ConfigDecoy;
use App\Http\Middleware\Decoys\ShellDecoy;
use App\Http\Middleware\Decoys\WordPressDecoy;

return [

    /*
    |--------------------------------------------------------------------------
    | Honeypot Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the middleware is a no-op. Useful when debugging a
    | misbehaving production incident where the tarpit itself is implicated.
    |
    */

    'enabled' => env('HONEYPOT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tarpit Delay
    |--------------------------------------------------------------------------
    |
    | Matching requests sleep for a random duration in this window before the
    | fake response is flushed. Disabled by default outside production so tests
    | and local scanning (for real) don't hang.
    |
    */

    'tarpit_enabled' => env('HONEYPOT_TARPIT_ENABLED', env('APP_ENV') === 'production'),

    'tarpit_min_seconds' => (int) env('HONEYPOT_TARPIT_MIN', 15),
    'tarpit_max_seconds' => (int) env('HONEYPOT_TARPIT_MAX', 45),

    /*
    |--------------------------------------------------------------------------
    | Concurrent Tarpit Cap
    |--------------------------------------------------------------------------
    |
    | Prevents worker pool exhaustion by limiting the number of simultaneously
    | sleeping requests. Above this cap the decoy response is flushed without
    | delay. Implemented as an atomic counter in the cache.
    |
    */

    'max_concurrent_tarpits' => (int) env('HONEYPOT_MAX_CONCURRENT', 10),

    /*
    |--------------------------------------------------------------------------
    | Blocklist TTL
    |--------------------------------------------------------------------------
    |
    | How long an IP that tripped the honeypot stays on the blocklist. While
    | listed, every subsequent request from that IP (on any path) is tarpitted.
    |
    */

    'blocklist_ttl_seconds' => (int) env('HONEYPOT_BLOCKLIST_TTL', 48 * 60 * 60),

    /*
    |--------------------------------------------------------------------------
    | ACME Handling
    |--------------------------------------------------------------------------
    |
    | Set to false temporarily if you need real ACME challenges to pass
    | through (e.g. during a manual certificate rotation).
    |
    */

    'handle_acme' => env('HONEYPOT_HANDLE_ACME', true),

    /*
    |--------------------------------------------------------------------------
    | Exempt Paths
    |--------------------------------------------------------------------------
    |
    | Exact-match or wildcard paths that bypass the honeypot entirely. Used
    | for health checks and any internal endpoints that must never stall.
    |
    */

    'exempt_paths' => [
        'up',
        'health',
        'healthz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exempt User Agents
    |--------------------------------------------------------------------------
    |
    | Case-insensitive substring match against the User-Agent header. A safety
    | valve for legitimate crawlers that might (incorrectly) probe WP paths.
    |
    */

    'exempt_user_agents' => [
        'Googlebot',
        'Bingbot',
        'DuckDuckBot',
        'Slurp',
        'Applebot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attack Vectors
    |--------------------------------------------------------------------------
    |
    | Each entry maps a URL pattern (Laravel `Str::is` syntax, no leading slash)
    | to a decoy handler class and an attack category. Add new patterns here
    | — no code changes required. The first matching pattern wins.
    |
    */

    'vectors' => [
        // WordPress core
        ['pattern' => 'wp-login.php', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'login'],
        ['pattern' => 'wp-admin', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'admin'],
        ['pattern' => 'wp-admin/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'admin'],
        ['pattern' => 'wp-includes/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'includes'],
        ['pattern' => 'xmlrpc.php', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'xmlrpc'],
        ['pattern' => 'wp-json/wp/v2/users', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'users'],
        ['pattern' => 'wp-json/wp/v2/users/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'users'],
        ['pattern' => 'wp-config.php', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'config'],
        ['pattern' => 'wp-config.php.bak', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'config'],
        ['pattern' => 'wp-config.php~', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'config'],
        ['pattern' => 'readme.html', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'readme'],
        ['pattern' => 'license.txt', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'license'],
        ['pattern' => 'wp-cron.php', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'cron'],

        // WP plugins
        ['pattern' => 'wp-content/plugins/revslider/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'plugin_revslider'],
        ['pattern' => 'wp-content/plugins/wp-file-manager/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'plugin_filemanager'],
        ['pattern' => 'wp-content/plugins/duplicator/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'plugin_duplicator'],
        ['pattern' => 'wp-content/plugins/contact-form-7/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'plugin_cf7'],
        ['pattern' => 'wp-content/plugins/elementor/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'plugin_elementor'],
        ['pattern' => 'wp-content/plugins/woocommerce/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'plugin_woocommerce'],
        ['pattern' => 'wp-content/*', 'decoy' => WordPressDecoy::class, 'category' => 'wordpress', 'variant' => 'content'],

        // Config / credential exposure
        ['pattern' => '.env', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'env'],
        ['pattern' => '.env.backup', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'env'],
        ['pattern' => '.env.old', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'env'],
        ['pattern' => '.env.production', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'env'],
        ['pattern' => '.git/config', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'git_config'],
        ['pattern' => '.git/HEAD', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'git_head'],
        ['pattern' => '.aws/credentials', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'aws'],
        ['pattern' => 'config.php', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'php_config'],
        ['pattern' => 'configuration.php', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'php_config'],
        ['pattern' => 'phpinfo.php', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'phpinfo'],
        ['pattern' => 'info.php', 'decoy' => ConfigDecoy::class, 'category' => 'config', 'variant' => 'phpinfo'],

        // Admin panel probes
        ['pattern' => 'phpmyadmin', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'phpmyadmin'],
        ['pattern' => 'phpmyadmin/*', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'phpmyadmin'],
        ['pattern' => 'pma', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'phpmyadmin'],
        ['pattern' => 'pma/*', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'phpmyadmin'],
        ['pattern' => 'mysql', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'phpmyadmin'],
        ['pattern' => 'mysql/*', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'phpmyadmin'],
        ['pattern' => 'adminer.php', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'adminer'],
        ['pattern' => 'admin', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'generic'],
        ['pattern' => 'administrator', 'decoy' => AdminDecoy::class, 'category' => 'admin', 'variant' => 'generic'],

        // Shell / backdoor probes
        ['pattern' => 'shell.php', 'decoy' => ShellDecoy::class, 'category' => 'shell', 'variant' => 'generic'],
        ['pattern' => 'c99.php', 'decoy' => ShellDecoy::class, 'category' => 'shell', 'variant' => 'c99'],
        ['pattern' => 'r57.php', 'decoy' => ShellDecoy::class, 'category' => 'shell', 'variant' => 'r57'],
        ['pattern' => 'wso.php', 'decoy' => ShellDecoy::class, 'category' => 'shell', 'variant' => 'wso'],

        // ACME
        ['pattern' => '.well-known/acme-challenge/*', 'decoy' => AcmeDecoy::class, 'category' => 'acme', 'variant' => 'challenge'],
    ],

];
