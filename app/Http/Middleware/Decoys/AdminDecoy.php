<?php

declare(strict_types=1);

namespace App\Http\Middleware\Decoys;

use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminDecoy implements Decoy
{
    public function render(Request $request, string $variant): Response
    {
        return match ($variant) {
            'phpmyadmin' => $this->phpMyAdmin(),
            'adminer' => $this->adminer(),
            'generic' => $this->genericAdmin(),
            default => $this->genericAdmin(),
        };
    }

    private function token(): string
    {
        return str()->random(32);
    }

    private function phpMyAdmin(): Response
    {
        $version = '4.'.random_int(7, 9).'.'.random_int(0, 12);
        $token = $this->token();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>phpMyAdmin</title>
<meta name="generator" content="phpMyAdmin {$version}">
<link rel="stylesheet" href="/phpmyadmin/themes/pmahomme/css/theme.css">
</head>
<body class="loginform">
<div class="container">
<a href="https://www.phpmyadmin.net/" target="_blank">
<img src="/phpmyadmin/themes/pmahomme/img/logo_right.png" alt="phpMyAdmin"/>
</a>
<h1>Welcome to <bdo dir="ltr">phpMyAdmin</bdo></h1>
<form method="post" action="/phpmyadmin/index.php" name="login_form" class="login hide js-show">
<fieldset>
<legend>Log in</legend>
<div class="item"><label for="input_username">Username:</label>
<input type="text" name="pma_username" id="input_username" value="" size="24" class="textfield" autofocus="autofocus"/></div>
<div class="item"><label for="input_password">Password:</label>
<input type="password" name="pma_password" id="input_password" value="" size="24" class="textfield"/></div>
<div class="item"><label for="select_server">Server Choice:</label>
<select name="server" id="select_server">
<option value="1">localhost</option>
</select></div>
<input type="hidden" name="token" value="{$token}"/>
</fieldset>
<fieldset class="tblFooters"><input value="Go" type="submit" id="input_go"/></fieldset>
</form>
</div>
</body></html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function adminer(): Response
    {
        $version = '4.'.random_int(7, 8).'.'.random_int(0, 5);
        $token = $this->token();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head><meta charset="utf-8"><title>Login - Adminer</title>
<link rel="stylesheet" href="/static/default.css">
<meta name="generator" content="Adminer {$version}"></head>
<body class="ltr">
<h1>Adminer <span class="version">{$version}</span></h1>
<form action="" method="post">
<table cellspacing="0">
<tr><th>System</th><td><select name="auth[driver]"><option value="server">MySQL</option></select></td></tr>
<tr><th>Server</th><td><input name="auth[server]" value="localhost"></td></tr>
<tr><th>Username</th><td><input name="auth[username]" autocomplete="username"></td></tr>
<tr><th>Password</th><td><input type="password" name="auth[password]" autocomplete="current-password"></td></tr>
<tr><th>Database</th><td><input name="auth[db]" value=""></td></tr>
</table>
<p><input type="submit" value="Login"><input type="hidden" name="token" value="{$token}"></p>
</form>
</body></html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function genericAdmin(): Response
    {
        $nonce = str()->random(12);

        $html = <<<HTML
<!DOCTYPE html>
<html><head><title>Admin Login</title></head>
<body>
<h1>Administrator Login</h1>
<form method="post" action="/admin/login">
<label>Username: <input type="text" name="username"></label>
<label>Password: <input type="password" name="password"></label>
<input type="hidden" name="csrf" value="{$nonce}">
<button type="submit">Login</button>
</form>
</body></html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
