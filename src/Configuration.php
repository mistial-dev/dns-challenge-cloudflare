<?php declare(strict_types=1);
/**
 * Configuration tree builder (for configuration file parsing)
 *
 * @author Kate Gray <opensource@codebykate.com>
 * @license https://unlicense.org/ Unlicense (Public Domain)
 */
namespace KateGray\DnsChallenge;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration implements ConfigurationInterface
{
    /**
     * Builds the configuration tree
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dns-challenge');
        $rootNode = $treeBuilder->getRootNode();

        /** @var ArrayNodeDefinition|NodeDefinition $rootNode */
        $rootNode->children()
            ->arrayNode('dns')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('record_name')
                        ->defaultValue('_acme-challenge')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('record_type')
                        ->defaultValue('TXT')
                        ->cannotBeEmpty()
                    ->end()
                    ->integerNode('record_ttl')
                        ->defaultValue(120)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('cloudflare')
                ->children()
                    ->scalarNode('account')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('api_key')
                        ->defaultNull()
                    ->end()
                    ->scalarNode('api_token')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end()
        ->end();

        $rootNode
            ->validate()
                ->ifTrue(static function ($v) {
                    $cf = $v['cloudflare'] ?? [];
                    $hasKey = !empty($cf['api_key']);
                    $hasToken = !empty($cf['api_token']);
                    $hasAccount = !empty($cf['account']);
                    if ($hasToken && $hasKey) {
                        return true;
                    }
                    if (!$hasToken && !$hasKey) {
                        return true;
                    }
                    if ($hasKey && !$hasAccount) {
                        return true;
                    }
                    return false;
                })
                ->thenInvalid('cloudflare config must include either api_token OR (account + api_key), but not both.')
            ->end();

        return $treeBuilder;
    }

}
