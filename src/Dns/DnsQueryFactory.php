<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

interface DnsQueryFactory
{
    public function create(string $server, int $timeoutSeconds): DnsQueryInterface;
}
