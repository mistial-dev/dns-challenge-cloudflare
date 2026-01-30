<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

interface DnsQueryInterface
{
    /**
     * @return iterable|false
     */
    public function query(string $question, string $typeName);

    /**
     * @return iterable
     */
    public function getLastadditional(): iterable;

    public function getLasterror(): string;
}
