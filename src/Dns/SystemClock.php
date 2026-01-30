<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

class SystemClock implements ClockInterface
{
    public function now(): float
    {
        return microtime(true);
    }
}
