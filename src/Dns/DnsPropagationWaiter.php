<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Dns;

use Exception;
use PurplePixie\PhpDns\DNSTypes;
use Symfony\Component\Console\Output\OutputInterface;

class DnsPropagationWaiter
{
    public function __construct(
        private DnsQueryFactory $queryFactory,
        private ClockInterface $clock,
        private SleeperInterface $sleeper,
        private string $resolverServer = '8.8.8.8',
        private int $queryTimeoutSeconds = 5,
        private int $propagationTimeoutSeconds = 120,
        private int $pollIntervalSeconds = 2,
        private string $checkMode = 'ipv4',
        private int $fixedDelaySeconds = 0,
    ) {
    }

    public function waitForTxt(string $zoneName, string $record, string $challenge, OutputInterface $output): void
    {
        if ($this->checkMode === 'none') {
            $delay = $this->fixedDelaySeconds > 0 ? $this->fixedDelaySeconds : $this->propagationTimeoutSeconds;
            $output->writeln(sprintf(
                'Skipping DNS propagation checks; sleeping for %d seconds before continuing.',
                $delay
            ));
            if ($delay > 0) {
                $this->sleeper->sleep($delay);
            }
            return;
        }

        $deadline = $this->clock->now() + $this->propagationTimeoutSeconds;
        $expected = trim($challenge, "\" \t\r\n");
        $servers = $this->resolveAuthoritativeServers($zoneName);

        $output->writeln(sprintf(
            'Waiting up to %d seconds for %s TXT to propagate via authoritative servers: %s',
            $this->propagationTimeoutSeconds,
            $record,
            implode(', ', $servers)
        ));

        while ($this->clock->now() < $deadline) {
            foreach ($servers as $server) {
                $query = $this->queryFactory->create($server, $this->queryTimeoutSeconds);
                $answer = $query->query($record, DNSTypes::NAME_TXT);
                if (false === $answer) {
                    continue;
                }

                foreach ($answer as $result) {
                    if ($result->getTypeid() !== DNSTypes::ID_TXT) {
                        continue;
                    }
                    $data = $result->getData();
                    if ($data === $expected || str_contains($data, $expected)) {
                        return;
                    }
                }
            }

            $this->sleeper->sleep($this->pollIntervalSeconds);
        }

        throw new Exception('TXT record did not propagate to authoritative servers within the timeout window.');
    }

    private function resolveAuthoritativeServers(string $zoneName): array
    {
        $zoneName = rtrim($zoneName, '.');
        $resolverQuery = $this->queryFactory->create($this->resolverServer, $this->queryTimeoutSeconds);
        $answer = $resolverQuery->query($zoneName, DNSTypes::NAME_NS);

        if (false === $answer || count($answer) === 0) {
            $err = $resolverQuery->getLasterror();
            throw new Exception(sprintf(
                'Unable to resolve authoritative name servers for %s via %s%s',
                $zoneName,
                $this->resolverServer,
                $err !== '' ? ': ' . $err : ''
            ));
        }

        $nsHosts = [];
        foreach ($answer as $result) {
            if ($result->getTypeid() === DNSTypes::ID_NS) {
                $nsHosts[] = rtrim($result->getData(), '.');
            }
        }

        $servers = $this->resolveNameserverIps($resolverQuery, $nsHosts);

        if (empty($servers)) {
            throw new Exception('Unable to resolve IPs for authoritative name servers.');
        }

        return $servers;
    }

    private function resolveNameserverIps(DnsQueryInterface $resolverQuery, array $nsHosts): array
    {
        $servers = [];
        $additional = $resolverQuery->getLastadditional();
        $allowedTypes = $this->allowedTypes();

        foreach ($nsHosts as $host) {
            $host = rtrim($host, '.');
            $found = false;

            foreach ($additional as $result) {
                $domain = rtrim($result->getDomain(), '.');
                if ($domain !== $host) {
                    continue;
                }
                if (in_array($result->getTypeid(), $allowedTypes, true)) {
                    $servers[] = $result->getData();
                    $found = true;
                }
            }

            if ($found) {
                continue;
            }

            foreach ($this->allowedTypeNames() as $type) {
                $hostAnswer = $resolverQuery->query($host, $type);
                if (false === $hostAnswer) {
                    continue;
                }
                foreach ($hostAnswer as $result) {
                    if (in_array($result->getTypeid(), $allowedTypes, true)) {
                        $servers[] = $result->getData();
                    }
                }
            }
        }

        $servers = array_values(array_unique($servers));
        return $servers;
    }

    private function allowedTypes(): array
    {
        return match ($this->checkMode) {
            'ipv6' => [DNSTypes::ID_AAAA],
            'both' => [DNSTypes::ID_A, DNSTypes::ID_AAAA],
            default => [DNSTypes::ID_A],
        };
    }

    private function allowedTypeNames(): array
    {
        return match ($this->checkMode) {
            'ipv6' => [DNSTypes::NAME_AAAA],
            'both' => [DNSTypes::NAME_A, DNSTypes::NAME_AAAA],
            default => [DNSTypes::NAME_A],
        };
    }
}
