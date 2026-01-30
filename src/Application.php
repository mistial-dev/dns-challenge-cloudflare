<?php declare(strict_types=1);
/**
 * Main application class
 *
 * @author Kate Gray <opensource@codebykate.com>
 * @license https://unlicense.org/ Unlicense (Public Domain)
 */
namespace KateGray\DnsChallenge;

use KateGray\DnsChallenge\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use \Symfony\Component\Console\Application as SymfonyApplication;
use \SelfUpdate\SelfUpdateCommand;
use \SelfUpdate\SelfUpdateManager;
use Symfony\Component\Yaml\Yaml;

class Application extends SymfonyApplication {
    const APP_NAME = 'DNS Challenge Utility for Cloudflare(r)';
    const APP_VERSION = '2.0';
    const GITHUB_REPO = 'mistial-dev/dns-challenge-cloudflare';
    const APP_CONFIG = '/etc/dns-challenge.yml';

    private $_config = false;

    /**
     * Application constructor.
     */
    public function __construct () {
        parent::__construct(self::APP_NAME, self::APP_VERSION);
        $this->add(new SetupCommand());
        $this->add(new TeardownCommand());
        $selfUpdateManager = new SelfUpdateManager(self::APP_NAME, self::APP_VERSION, self::GITHUB_REPO);
        $this->add(new SelfUpdateCommand($selfUpdateManager));
    }

    /**
     * Parse the configuration file located in /etc/dns-challenge.yml
     * @return array Parsed configuration
     */
    public function getConfig (): array
    {
        if (false !== $this->_config) {
            return $this->_config;
        }
        if (!file_exists (self::APP_CONFIG) || !is_readable(self::APP_CONFIG)) {
            throw new InvalidConfigurationException('Configuration file does not exist or is not readable');
        }
        $configData = Yaml::parse(file_get_contents(self::APP_CONFIG));
        $config = new Configuration();

        $processor = new Processor();
        $this->_config = $processor->processConfiguration($config, [$configData]);
        return $this->_config;
    }

}
