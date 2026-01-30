<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Tests;

use Exception;
use KateGray\DnsChallenge\Dns\ClockInterface;
use KateGray\DnsChallenge\Dns\DnsPropagationWaiter;
use KateGray\DnsChallenge\Dns\DnsQueryFactory;
use KateGray\DnsChallenge\Dns\DnsQueryInterface;
use KateGray\DnsChallenge\Dns\SleeperInterface;
use PHPUnit\Framework\TestCase;
use PurplePixie\PhpDns\DNSAnswer;
use PurplePixie\PhpDns\DNSResult;
use PurplePixie\PhpDns\DNSTypes;
use Symfony\Component\Console\Output\NullOutput;

final class DnsPropagationWaiterTest extends TestCase
{
    public function testWaitForTxtSucceedsWhenAuthoritativeHasRecord(): void
    {
        $responses = [
            '8.8.8.8' => [
                DNSTypes::NAME_NS => [
                    'mistial.dev' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_NS, 'ns1.example.net', 'mistial.dev'),
                        ]),
                        'additional' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_A, '192.0.2.1', 'ns1.example.net'),
                        ]),
                    ],
                ],
            ],
            '192.0.2.1' => [
                DNSTypes::NAME_TXT => [
                    '_acme-challenge.mistial.dev' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_TXT, 'token-value', '_acme-challenge.mistial.dev'),
                        ]),
                    ],
                ],
            ],
        ];

        $log = [];
        $factory = new FakeQueryFactory($responses, $log);
        $waiter = new DnsPropagationWaiter(
            $factory,
            new FakeClock(),
            new FakeSleeper()
        );

        $waiter->waitForTxt('mistial.dev', '_acme-challenge.mistial.dev', 'token-value', new NullOutput());

        $this->assertTrue($this->logHasQuery($log, '192.0.2.1', '_acme-challenge.mistial.dev', DNSTypes::NAME_TXT));
    }

    public function testResolvesNameserverIpsWhenAdditionalSectionMissing(): void
    {
        $responses = [
            '8.8.8.8' => [
                DNSTypes::NAME_NS => [
                    'mistial.dev' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_NS, 'ns1.example.net', 'mistial.dev'),
                        ]),
                        'additional' => $this->makeAnswer([]),
                    ],
                ],
                DNSTypes::NAME_A => [
                    'ns1.example.net' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_A, '192.0.2.9', 'ns1.example.net'),
                        ]),
                    ],
                ],
            ],
            '192.0.2.9' => [
                DNSTypes::NAME_TXT => [
                    '_acme-challenge.mistial.dev' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_TXT, 'ok', '_acme-challenge.mistial.dev'),
                        ]),
                    ],
                ],
            ],
        ];

        $log = [];
        $factory = new FakeQueryFactory($responses, $log);
        $waiter = new DnsPropagationWaiter(
            $factory,
            new FakeClock(),
            new FakeSleeper()
        );

        $waiter->waitForTxt('mistial.dev', '_acme-challenge.mistial.dev', 'ok', new NullOutput());

        $this->assertTrue($this->logHasQuery($log, '8.8.8.8', 'ns1.example.net', DNSTypes::NAME_A));
        $this->assertTrue($this->logHasQuery($log, '192.0.2.9', '_acme-challenge.mistial.dev', DNSTypes::NAME_TXT));
    }

    public function testTimeoutThrowsException(): void
    {
        $responses = [
            '8.8.8.8' => [
                DNSTypes::NAME_NS => [
                    'mistial.dev' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_NS, 'ns1.example.net', 'mistial.dev'),
                        ]),
                        'additional' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_A, '192.0.2.2', 'ns1.example.net'),
                        ]),
                    ],
                ],
            ],
            '192.0.2.2' => [
                DNSTypes::NAME_TXT => [
                    '_acme-challenge.mistial.dev' => [
                        'answer' => $this->makeAnswer([]),
                    ],
                ],
            ],
        ];

        $log = [];
        $factory = new FakeQueryFactory($responses, $log);
        $clock = new FakeClock();
        $sleeper = new FakeSleeper($clock);
        $waiter = new DnsPropagationWaiter(
            $factory,
            $clock,
            $sleeper,
            '8.8.8.8',
            5,
            3,
            1,
            'ipv4',
            0
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('TXT record did not propagate');

        $waiter->waitForTxt('mistial.dev', '_acme-challenge.mistial.dev', 'missing', new NullOutput());
    }

    public function testCheckModeNoneUsesFixedDelay(): void
    {
        $log = [];
        $factory = new FakeQueryFactory([], $log);
        $clock = new FakeClock();
        $sleeper = new FakeSleeper($clock);
        $waiter = new DnsPropagationWaiter(
            $factory,
            $clock,
            $sleeper,
            '8.8.8.8',
            5,
            120,
            2,
            'none',
            10
        );

        $waiter->waitForTxt('mistial.dev', '_acme-challenge.mistial.dev', 'token', new NullOutput());

        $this->assertSame(10, $sleeper->getSleptSeconds());
        $this->assertCount(0, $log);
    }

    public function testIpv4ModeIgnoresIpv6OnlyNameservers(): void
    {
        $responses = [
            '8.8.8.8' => [
                DNSTypes::NAME_NS => [
                    'mistial.dev' => [
                        'answer' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_NS, 'ns1.example.net', 'mistial.dev'),
                        ]),
                        'additional' => $this->makeAnswer([
                            $this->makeResult(DNSTypes::ID_AAAA, '2001:db8::1', 'ns1.example.net'),
                        ]),
                    ],
                ],
                DNSTypes::NAME_A => [
                    'ns1.example.net' => [
                        'answer' => $this->makeAnswer([]),
                    ],
                ],
            ],
        ];

        $log = [];
        $factory = new FakeQueryFactory($responses, $log);
        $waiter = new DnsPropagationWaiter(
            $factory,
            new FakeClock(),
            new FakeSleeper(),
            '8.8.8.8',
            5,
            10,
            1,
            'ipv4',
            0
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to resolve IPs for authoritative name servers.');

        $waiter->waitForTxt('mistial.dev', '_acme-challenge.mistial.dev', 'token', new NullOutput());
    }

    private function logHasQuery(array $log, string $server, string $question, string $type): bool
    {
        foreach ($log as $entry) {
            if ($entry['server'] === $server && $entry['question'] === $question && $entry['type'] === $type) {
                return true;
            }
        }
        return false;
    }

    private function makeAnswer(array $results): DNSAnswer
    {
        $answer = new DNSAnswer();
        foreach ($results as $result) {
            $answer->addResult($result);
        }
        return $answer;
    }

    private function makeResult(int $typeId, string $data, string $domain): DNSResult
    {
        $types = new DNSTypes();
        $typename = $types->getNameById($typeId);
        return new DNSResult($typename, $typeId, 'IN', 60, $data, $domain, $domain . ' ' . $typename . ' ' . $data, []);
    }
}

final class FakeClock implements ClockInterface
{
    private float $now;

    public function __construct(float $start = 0.0)
    {
        $this->now = $start;
    }

    public function now(): float
    {
        return $this->now;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}

final class FakeSleeper implements SleeperInterface
{
    public function __construct(private ?FakeClock $clock = null)
    {
    }

    private int $sleptSeconds = 0;

    public function sleep(int $seconds): void
    {
        $this->sleptSeconds += $seconds;
        if ($this->clock) {
            $this->clock->advance($seconds);
        }
    }

    public function getSleptSeconds(): int
    {
        return $this->sleptSeconds;
    }
}

final class FakeQueryFactory implements DnsQueryFactory
{
    /**
     * @param array<string, array<string, array<string, array{answer: DNSAnswer|false, additional?: DNSAnswer}>>> $responses
     * @param array<int, array{server: string, question: string, type: string}> $log
     */
    public function __construct(private array $responses, private array &$log)
    {
    }

    public function create(string $server, int $timeoutSeconds): DnsQueryInterface
    {
        return new FakeQuery($server, $this->responses[$server] ?? [], $this->log);
    }
}

final class FakeQuery implements DnsQueryInterface
{
    private iterable $lastAdditional;

    /**
     * @param array<string, array<string, array{answer: DNSAnswer|false, additional?: DNSAnswer}>> $responses
     * @param array<int, array{server: string, question: string, type: string}> $log
     */
    public function __construct(
        private string $server,
        private array $responses,
        private array &$log
    ) {
        $this->lastAdditional = new DNSAnswer();
    }

    public function query(string $question, string $typeName)
    {
        $this->log[] = [
            'server' => $this->server,
            'question' => $question,
            'type' => $typeName,
        ];

        $entry = $this->responses[$typeName][$question] ?? null;
        if ($entry !== null) {
            $this->lastAdditional = $entry['additional'] ?? new DNSAnswer();
            return $entry['answer'];
        }

        $this->lastAdditional = new DNSAnswer();
        return new DNSAnswer();
    }

    public function getLastadditional(): iterable
    {
        return $this->lastAdditional;
    }

    public function getLasterror(): string
    {
        return '';
    }
}
