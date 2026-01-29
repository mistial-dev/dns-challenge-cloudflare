<?php declare(strict_types=1);
/**
 * Main application class
 *
 * @author Kate Gray <opensource@codebykate.com>
 * @license https://unlicense.org/ Unlicense (Public Domain)
 */
namespace KateGray\DnsChallenge;

use Cloudflare\API\Endpoints\EndpointException;
use \Exception;
use \Cloudflare\API\Auth\APIKey;
use \Cloudflare\API\Auth\APIToken;
use \Cloudflare\API\Adapter\Guzzle;
use \Cloudflare\API\Endpoints\DNS;
use \Cloudflare\API\Endpoints\Zones;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupCommand extends Command {
    // Delay (in seconds) after sending command before returning to mod_md
    protected const WAIT_DELAY = 5;

    protected function configure()
    {
        $this->setDescription ('Adds an ACME challenge to a CloudFlare zone')
             ->setName('setup')
             ->setHelp('This command takes the provided zone and acme domain, ' .
                 'makes an API call to Cloudflare, and adds the required record.')
             ->addArgument('zone', InputArgument::REQUIRED,"Zone (domain name)")
             ->addArgument('challenge', InputArgument::REQUIRED, 'ACME Challenge');
    }

    /**
     * Set up the DNS entry
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Exit code for the command line interface
     * @throws EndpointException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var Application $application */
            $application = $this->getApplication();
            $config = $application->getConfig();

        $api_account = $config['cloudflare']['account'];
        $api_key     = $config['cloudflare']['api_key'];
        $api_token   = $config['cloudflare']['api_token'];
            $record_name = $config['dns']['record_name'];
            $record_type = $config['dns']['record_type'];
            $record_ttl  = $config['dns']['record_ttl'];
            $zone_name   = $input->getArgument('zone');
            $challenge   = $input->getArgument('challenge');

        // Generate an auth object and instantiate the API endpoints
        if (!empty($api_token)) {
            $auth = new APIToken($api_token);
        } else {
            $auth = new APIKey($api_account, $api_key);
        }
        $adapter = new Guzzle($auth);
            $zones   = new Zones($adapter);
            $dns     = new DNS($adapter);

            // Concatenate the record to the zone name (required for API)
            $record = sprintf ('%s.%s', $record_name, $zone_name);

            // Look up the zone
            $zone_id = $zones->getZoneID($zone_name);
            if (!$zone_id) {
                throw new Exception('Unable to get ID for zone.');
            }

            // Check for an existing record and delete it if present
            $record_id = $dns->getRecordID($zone_id, $record_type, $record);
            if ('' != $record_id) {
                // Existing record, delete
                $result = $dns->deleteRecord($zone_id, $record_id);

                if (!$result) throw new Exception ('Unable to delete record from Cloudflare.');
            } else {
                $result = true;
            }

            // Only add a new challenge if there is a challenge to add
            if (false !== $challenge) {
                // Create a new record
                $result = $dns->addRecord($zone_id, $record_type, $record,
                    $challenge, $record_ttl, false);
            }

            // Pause here in order to give cloudflare a chance before exiting
            // This helps keep mod_md from cycling too often while this propagages
            sleep(self::WAIT_DELAY);

            // True if both the challenge and delete succeed
            if (true === $result) {
                $output->writeln('Record update <info>successful</info>.');
                return Command::SUCCESS;
            }
            throw new Exception ('Unable to perform operation.');
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if (null !== $response) {
                $body = (string) $response->getBody();
                if ($body !== '') {
                    $output->writeln('<error>Cloudflare response body:</error>');
                    $output->writeln($body);
                }
            }
            throw $e;
        }
    }
}
