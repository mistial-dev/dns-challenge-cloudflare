<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

use PurplePixie\PhpDns\DNSQuery;

class PhpDnsQueryAdapter implements DnsQueryInterface
{
    public function __construct(private DNSQuery $query)
    {
    }

    public function query(string $question, string $typeName)
    {
        return $this->query->query($question, $typeName);
    }

    public function getLastadditional(): iterable
    {
        return $this->query->getLastadditional();
    }

    public function getLasterror(): string
    {
        return $this->query->getLasterror();
    }
}
