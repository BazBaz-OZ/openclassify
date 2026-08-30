<?php

declare(strict_types=1);

namespace Modules\User\App\Support;

use Illuminate\Support\Str;

final class DemoUserCatalog
{
    public static function records(): array
    {
        $password = self::resolvePassword();

        return [
            [
                'email' => 'a@a.com',
                'name' => 'Admin',
                'password' => $password,
                'phone' => '+61491570006',
                'is_admin' => true,
            ],
            [
                'email' => 'b@b.com',
                'name' => 'Member',
                'password' => $password,
                'phone' => '+61491570156',
                'is_admin' => false,
            ],
            [
                'email' => 'c@c.com',
                'name' => 'Ava Carter',
                'password' => $password,
                'phone' => '+61491570157',
                'is_admin' => false,
            ],
            [
                'email' => 'd@d.com',
                'name' => 'Liam Stone',
                'password' => $password,
                'phone' => '+61491570158',
                'is_admin' => false,
            ],
            [
                'email' => 'e@e.com',
                'name' => 'Mila Reed',
                'password' => $password,
                'phone' => '+61491570159',
                'is_admin' => false,
            ],
        ];
    }

    public static function resolvePassword(): string
    {
        $configured = trim((string) config('demo.user_password', ''));

        if ($configured !== '') {
            return $configured;
        }

        return Str::password(20);
    }

    public static function emails(): array
    {
        return array_column(self::records(), 'email');
    }

    public static function phoneFor(string $email): string
    {
        foreach (self::records() as $record) {
            if ($record['email'] === $email) {
                return $record['phone'];
            }
        }

        return '+61491570110';
    }

    public static function isAdmin(string $email): bool
    {
        foreach (self::records() as $record) {
            if ($record['email'] === $email) {
                return (bool) $record['is_admin'];
            }
        }

        return false;
    }
}
