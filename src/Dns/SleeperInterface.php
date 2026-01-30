<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

interface SleeperInterface
{
    public function sleep(int $seconds): void;
}
