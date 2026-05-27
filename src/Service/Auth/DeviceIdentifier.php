<?php

namespace App\Service\Auth;

final class DeviceIdentifier
{
    public const COOKIE_NAME = 'DEVICE_ID';

    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
