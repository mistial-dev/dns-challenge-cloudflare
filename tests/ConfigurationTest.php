<?php declare(strict_types=1);

namespace KateGray\DnsChallenge\Tests;

use KateGray\DnsChallenge\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testInvalidPropagationCheckFailsValidation(): void
    {
        $processor = new Processor();
        $config = new Configuration();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('dns.propagation_check must be one of');

        $processor->processConfiguration($config, [[
            'dns' => [
                'propagation_check' => 'bad',
            ],
            'cloudflare' => [
                'api_token' => 'token',
            ],
        ]]);
    }

    public function testValidPropagationCheckPasses(): void
    {
        $processor = new Processor();
        $config = new Configuration();

        $result = $processor->processConfiguration($config, [[
            'dns' => [
                'propagation_check' => 'ipv6',
            ],
            'cloudflare' => [
                'api_token' => 'token',
            ],
        ]]);

        $this->assertSame('ipv6', $result['dns']['propagation_check']);
    }

    public function testNonePropagationRequiresDelayOrTimeout(): void
    {
        $processor = new Processor();
        $config = new Configuration();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('propagation_check=none requires');

        $processor->processConfiguration($config, [[
            'dns' => [
                'propagation_check' => 'none',
                'propagation_fixed_delay' => 0,
                'propagation_timeout' => 0,
            ],
            'cloudflare' => [
                'api_token' => 'token',
            ],
        ]]);
    }
}
