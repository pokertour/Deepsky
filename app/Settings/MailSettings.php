<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public ?string $host;

    public ?int $port;

    public ?string $username;

    public ?string $password;

    public ?string $scheme;

    public ?string $from_address;

    public ?string $from_name;

    public static function group(): string
    {
        return 'mail';
    }

    public static function encrypted(): array
    {
        return [
            'password',
        ];
    }

    /**
     * @return array{transport: string, host: ?string, port: ?int, username: ?string, password: ?string, scheme: ?string}
     */
    public function toSmtpMailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'scheme' => $this->scheme,
        ];
    }
}
