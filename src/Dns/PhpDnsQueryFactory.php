<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

use PurplePixie\PhpDns\DNSQuery;

class PhpDnsQueryFactory implements DnsQueryFactory
{
    public function create(string $server, int $timeoutSeconds): DnsQueryInterface
    {
        return new PhpDnsQueryAdapter(new DNSQuery($server, 53, $timeoutSeconds));
    }
}
