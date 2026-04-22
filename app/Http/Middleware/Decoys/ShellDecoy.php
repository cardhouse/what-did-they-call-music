<?php

declare(strict_types=1);

namespace App\Http\Middleware\Decoys;

use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class ShellDecoy implements Decoy
{
    public function render(Request $request, string $variant): Response
    {
        $banner = match ($variant) {
            'c99' => 'C99Shell v. 2.0',
            'r57' => 'r57shell 1.40',
            'wso' => 'WSO 2.5.1',
            default => 'Shell '.str()->random(6),
        };

        return $this->shell($banner);
    }

    private function shell(string $banner): Response
    {
        $uname = 'Linux web-'.strtolower(str()->random(4)).' 5.4.0-'.random_int(100, 180).'-generic';
        $user = 'www-data';
        $uid = '33';
        $pwd = '/var/www/html';
        $phpVersion = '7.2.'.random_int(10, 34);
        $sessionId = str()->random(26);

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<title>{$banner}</title>
<style>
body { background:#000; color:#0f0; font-family:monospace; font-size:13px; padding:10px; }
table { border:1px solid #0f0; width:100%; }
td { padding:3px 6px; border:1px solid #083008; }
a { color:#5f5; }
textarea { width:100%; background:#000; color:#0f0; border:1px solid #0f0; }
input[type=text], input[type=submit] { background:#000; color:#0f0; border:1px solid #0f0; }
</style>
</head>
<body>
<h1>{$banner}</h1>
<table>
<tr><td>Uname</td><td>{$uname}</td></tr>
<tr><td>User</td><td>{$uid} ({$user})</td></tr>
<tr><td>PHP</td><td>{$phpVersion} (Safe Mode: OFF)</td></tr>
<tr><td>HDD</td><td>Total: ".random_int(40, 500)." GB Free: ".random_int(5, 100)." GB</td></tr>
<tr><td>Cwd</td><td>{$pwd}</td></tr>
<tr><td>Session</td><td>{$sessionId}</td></tr>
</table>
<form method="post">
<p>Command: <input type="text" name="cmd" size="80"> <input type="submit" value="Execute"></p>
<textarea name="output" rows="20" readonly>[!] Already compromised by previous operator. Reuse at your own risk.
[+] Backdoor persistent: /tmp/.{$sessionId}
[+] Logs redirected to /dev/null
[+] Tripwire: disabled</textarea>
</form>
<p><a href="?act=phpinfo">PHPinfo</a> | <a href="?act=sql">SQL</a> | <a href="?act=eval">Eval</a> | <a href="?act=files">Files</a> | <a href="?act=kill">Self-Kill</a></p>
</body></html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
