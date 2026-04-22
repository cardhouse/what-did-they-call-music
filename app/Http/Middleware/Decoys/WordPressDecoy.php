<?php

declare(strict_types=1);

namespace App\Http\Middleware\Decoys;

use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class WordPressDecoy implements Decoy
{
    /** @var list<string> */
    private const FAKE_ADMIN_USERNAMES = [
        'admin', 'administrator', 'wpadmin', 'editor', 'webmaster',
        'support', 'manager', 'root', 'sysadmin', 'developer',
    ];

    /** @var list<string> */
    private const VULNERABLE_WP_VERSIONS = ['5.4.2', '5.4.1', '5.3.4', '5.2.7', '4.9.15'];

    public function render(Request $request, string $variant): Response
    {
        return match ($variant) {
            'login' => $this->loginPage(),
            'admin' => $this->adminRedirect(),
            'includes' => $this->includesStub(),
            'content' => $this->contentStub(),
            'xmlrpc' => $this->xmlrpc($request),
            'users' => $this->restUsers(),
            'config' => $this->wpConfig(),
            'readme' => $this->readme(),
            'license' => $this->license(),
            'cron' => $this->cron(),
            'plugin_revslider' => $this->pluginStub('revslider', '4.2.0'),
            'plugin_filemanager' => $this->pluginStub('wp-file-manager', '6.8'),
            'plugin_duplicator' => $this->pluginStub('duplicator', '1.3.26'),
            'plugin_cf7' => $this->pluginStub('contact-form-7', '5.1.6'),
            'plugin_elementor' => $this->pluginStub('elementor', '3.6.1'),
            'plugin_woocommerce' => $this->pluginStub('woocommerce', '5.5.1'),
            default => $this->loginPage(),
        };
    }

    private function wpVersion(): string
    {
        return self::VULNERABLE_WP_VERSIONS[array_rand(self::VULNERABLE_WP_VERSIONS)];
    }

    private function fakeUsername(): string
    {
        return self::FAKE_ADMIN_USERNAMES[array_rand(self::FAKE_ADMIN_USERNAMES)];
    }

    private function nonce(): string
    {
        return substr(md5((string) random_int(PHP_INT_MIN, PHP_INT_MAX)), 0, 10);
    }

    private function loginPage(): Response
    {
        $version = $this->wpVersion();
        $nonce = $this->nonce();
        $rnd = str()->random(8);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Log In &lsaquo; WordPress</title>
<meta name="generator" content="WordPress {$version}" />
<meta name="robots" content="noindex,follow" />
<link rel='stylesheet' id='login-css'  href='/wp-admin/css/login.min.css?ver={$version}' type='text/css' media='all' />
</head>
<body class="login login-action-login wp-core-ui locale-en-us">
<div id="login">
<h1><a href="https://wordpress.org/" tabindex="-1">Powered by WordPress</a></h1>
<form name="loginform" id="loginform" action="/wp-login.php" method="post">
<p><label for="user_login">Username or Email Address</label>
<input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" /></p>
<p><label for="user_pass">Password</label>
<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" /></p>
<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Log In" />
<input type="hidden" name="redirect_to" value="/wp-admin/" />
<input type="hidden" name="testcookie" value="1" />
<input type="hidden" name="_wpnonce" value="{$nonce}" />
<input type="hidden" name="_wp_http_referer" value="/wp-login.php?reauth={$rnd}" /></p>
</form>
</div>
</body>
</html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function adminRedirect(): Response
    {
        return $this->loginPage();
    }

    private function includesStub(): Response
    {
        $version = $this->wpVersion();

        return new IlluminateResponse(
            "<?php\n/* WordPress {$version} */\n// Silence is golden.\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function contentStub(): Response
    {
        return new IlluminateResponse(
            '<html><body><!-- generated '.str()->random(16)." --></body></html>\n",
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    private function xmlrpc(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $xml = <<<'XML'
<?xml version="1.0"?>
<methodResponse>
  <params>
    <param>
      <value>
        <array>
          <data>
            <value><string>system.multicall</string></value>
            <value><string>system.listMethods</string></value>
            <value><string>system.getCapabilities</string></value>
            <value><string>demo.sayHello</string></value>
            <value><string>demo.addTwoNumbers</string></value>
            <value><string>pingback.ping</string></value>
            <value><string>pingback.extensions.getPingbacks</string></value>
            <value><string>wp.getUsersBlogs</string></value>
            <value><string>wp.getPage</string></value>
            <value><string>wp.getPages</string></value>
            <value><string>wp.newPost</string></value>
            <value><string>wp.editPost</string></value>
            <value><string>wp.deletePost</string></value>
            <value><string>wp.getUsers</string></value>
            <value><string>wp.getProfile</string></value>
          </data>
        </array>
      </value>
    </param>
  </params>
</methodResponse>
XML;

            return new IlluminateResponse($xml, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
        }

        return new IlluminateResponse(
            "XML-RPC server accepts POST requests only.\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function restUsers(): Response
    {
        $users = [];
        $count = random_int(2, 5);
        $seen = [];

        for ($i = 0; $i < $count; $i++) {
            $name = $this->fakeUsername();
            if (in_array($name, $seen, true)) {
                continue;
            }
            $seen[] = $name;

            $users[] = [
                'id' => random_int(1, 50),
                'name' => ucfirst($name),
                'url' => '',
                'description' => '',
                'link' => '/author/'.$name.'/',
                'slug' => $name,
                'avatar_urls' => [
                    '24' => 'https://secure.gravatar.com/avatar/'.md5($name).'?s=24',
                    '48' => 'https://secure.gravatar.com/avatar/'.md5($name).'?s=48',
                    '96' => 'https://secure.gravatar.com/avatar/'.md5($name).'?s=96',
                ],
                'meta' => [],
                '_links' => [
                    'self' => [['href' => '/wp-json/wp/v2/users/'.random_int(1, 50)]],
                    'collection' => [['href' => '/wp-json/wp/v2/users']],
                ],
            ];
        }

        return new IlluminateResponse(
            json_encode(array_values($users), JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    private function wpConfig(): Response
    {
        $db = 'wp_'.strtolower(str()->random(8));
        $user = 'wpuser_'.strtolower(str()->random(6));
        $pass = str()->random(16);
        $host = '10.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254);
        $prefix = 'wp_'.strtolower(str()->random(4)).'_';

        $keys = collect(['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'])
            ->map(fn (string $k) => "define('{$k}', '".base64_encode(random_bytes(48))."');")
            ->implode("\n");

        $body = <<<PHP
<?php
/**
 * WordPress base configuration.
 */
define('DB_NAME', '{$db}');
define('DB_USER', '{$user}');
define('DB_PASSWORD', '{$pass}');
define('DB_HOST', '{$host}');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

{$keys}

\$table_prefix = '{$prefix}';

define('WP_DEBUG', false);

if ( ! defined('ABSPATH') ) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
PHP;

        return new IlluminateResponse(
            $body,
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function readme(): Response
    {
        $version = $this->wpVersion();

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>WordPress &#8250; ReadMe</title></head>
<body>
<h1 id="logo">WordPress</h1>
<p style="text-align: center;">Version {$version}</p>
<h1>Semantic Personal Publishing Platform</h1>
<p>WordPress is a publishing platform. This is version {$version}.</p>
<!-- build: {$this->nonce()} -->
</body>
</html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function license(): Response
    {
        $body = "WordPress - Web publishing software\n\n"
            .'Copyright 2011-'.date('Y')." by the contributors\n\n"
            ."This program is free software; you can redistribute it and/or modify\n"
            ."it under the terms of the GNU General Public License as published by\n"
            ."the Free Software Foundation; either version 2 of the License, or\n"
            ."(at your option) any later version.\n\n"
            .'# '.str()->random(12)."\n";

        return new IlluminateResponse($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function cron(): Response
    {
        return new IlluminateResponse('', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function pluginStub(string $slug, string $version): Response
    {
        $body = "/*\nPlugin Name: {$slug}\nVersion: {$version}\nAuthor: ".str()->random(8)."\n*/\n";

        return new IlluminateResponse($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
