<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

class SystemSleeper implements SleeperInterface
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
