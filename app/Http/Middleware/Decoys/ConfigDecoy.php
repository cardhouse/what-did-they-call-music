<?php

declare(strict_types=1);

namespace App\Http\Middleware\Decoys;

use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class ConfigDecoy implements Decoy
{
    public function render(Request $request, string $variant): Response
    {
        return match ($variant) {
            'env' => $this->laravelEnv(),
            'git_config' => $this->gitConfig(),
            'git_head' => $this->gitHead(),
            'aws' => $this->awsCredentials(),
            'php_config' => $this->phpConfig(),
            'phpinfo' => $this->phpInfo(),
            default => $this->laravelEnv(),
        };
    }

    private function privateIp(): string
    {
        $blocks = [
            ['10', (string) random_int(0, 255), (string) random_int(0, 255), (string) random_int(1, 254)],
            ['192', '168', (string) random_int(0, 255), (string) random_int(1, 254)],
            ['172', (string) random_int(16, 31), (string) random_int(0, 255), (string) random_int(1, 254)],
        ];

        return implode('.', $blocks[array_rand($blocks)]);
    }

    private function fakeAwsAccessKey(): string
    {
        $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $body = '';
        for ($i = 0; $i < 16; $i++) {
            $body .= $alpha[random_int(0, strlen($alpha) - 1)];
        }

        return 'AKIA'.$body;
    }

    private function fakeAwsSecret(): string
    {
        return base64_encode(random_bytes(30));
    }

    private function laravelEnv(): Response
    {
        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $dbHost = $this->privateIp();
        $dbName = 'app_'.strtolower(str()->random(6));
        $dbUser = 'app_'.strtolower(str()->random(5));
        $dbPass = str()->random(20);
        $redisHost = $this->privateIp();
        $mailUser = strtolower(str()->random(10));
        $mailPass = str()->random(16);
        $awsKey = $this->fakeAwsAccessKey();
        $awsSecret = $this->fakeAwsSecret();
        $awsBucket = 'prod-'.strtolower(str()->random(8));

        $body = <<<ENV
APP_NAME="Laravel"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL=https://app.example.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT=3306
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}

BROADCAST_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST={$redisHost}
REDIS_PASSWORD={$dbPass}
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME={$mailUser}
MAIL_PASSWORD={$mailPass}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

AWS_ACCESS_KEY_ID={$awsKey}
AWS_SECRET_ACCESS_KEY={$awsSecret}
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET={$awsBucket}
AWS_USE_PATH_STYLE_ENDPOINT=false
ENV;

        return new IlluminateResponse($body."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function gitConfig(): Response
    {
        $repo = strtolower(str()->random(8));
        $body = <<<INI
[core]
\trepositoryformatversion = 0
\tfilemode = true
\tbare = false
\tlogallrefupdates = true
[remote "origin"]
\turl = git@github.com:example/{$repo}.git
\tfetch = +refs/heads/*:refs/remotes/origin/*
[branch "main"]
\tremote = origin
\tmerge = refs/heads/main
INI;

        return new IlluminateResponse($body."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function gitHead(): Response
    {
        return new IlluminateResponse(
            "ref: refs/heads/main\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function awsCredentials(): Response
    {
        $body = "[default]\n"
            .'aws_access_key_id = '.$this->fakeAwsAccessKey()."\n"
            .'aws_secret_access_key = '.$this->fakeAwsSecret()."\n"
            ."region = us-east-1\n"
            ."\n[deploy]\n"
            .'aws_access_key_id = '.$this->fakeAwsAccessKey()."\n"
            .'aws_secret_access_key = '.$this->fakeAwsSecret()."\n"
            ."region = us-west-2\n";

        return new IlluminateResponse($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function phpConfig(): Response
    {
        $dbHost = $this->privateIp();
        $dbPass = str()->random(18);

        $body = "<?php\n"
            ."\$config = [];\n"
            ."\$config['db_host'] = '{$dbHost}';\n"
            ."\$config['db_user'] = 'app_".strtolower(str()->random(5))."';\n"
            ."\$config['db_pass'] = '{$dbPass}';\n"
            ."\$config['secret'] = '".base64_encode(random_bytes(24))."';\n";

        return new IlluminateResponse($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function phpInfo(): Response
    {
        $phpVersion = '7.2.'.random_int(10, 34);
        $nonce = str()->random(10);

        $html = <<<HTML
<!DOCTYPE html>
<html><head><title>phpinfo()</title></head><body>
<h1 class="p">PHP Version {$phpVersion}</h1>
<table>
<tr><td>System</td><td>Linux web-{$nonce} 5.4.0 #1 SMP x86_64</td></tr>
<tr><td>Build Date</td><td>Mar 3 2020 14:25:50</td></tr>
<tr><td>Server API</td><td>Apache 2.0 Handler</td></tr>
<tr><td>DOCUMENT_ROOT</td><td>/var/www/html</td></tr>
<tr><td>expose_php</td><td>On</td></tr>
<tr><td>display_errors</td><td>On</td></tr>
<tr><td>allow_url_fopen</td><td>On</td></tr>
<tr><td>allow_url_include</td><td>On</td></tr>
</table>
</body></html>
HTML;

        return new IlluminateResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
