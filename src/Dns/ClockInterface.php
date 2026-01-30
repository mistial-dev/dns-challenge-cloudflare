<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

interface ClockInterface
{
    public function now(): float;
}
