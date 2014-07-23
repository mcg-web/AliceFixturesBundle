<?php

/*
 * This file is part of the h4cc/AliceFixtureBundle package.
 *
 * (c) Julius Beckmann <github@h4cc.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace h4cc\AliceFixturesBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * Class h4ccAliceFixturesExtension
 *
 * @author Julius Beckmann <github@h4cc.de>
 */
class h4ccAliceFixturesExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (empty($config['default_manager'])) {
            $keys = array_keys($config['managers']);
            $config['default_manager'] = reset($keys);
        }

        $defaultManagerConfig = $config['managers'][$config['default_manager']];

        $currentManagerConfig = $defaultManagerConfig;

        // Set the default service ids, if they have not been defined in config.
        switch($currentManagerConfig['doctrine']) {
            case 'orm':
                if(!$currentManagerConfig['object_manager']) {
                    $currentManagerConfig['object_manager'] = 'doctrine.orm.entity_manager';
                }
                if(!$currentManagerConfig['schema_tool']) {
                    $currentManagerConfig['schema_tool'] = 'h4cc_alice_fixtures.orm.schema_tool.doctrine';
                }
                break;
            case 'mongodb-odm':
                if(!$currentManagerConfig['object_manager']) {
                    $config['object_manager'] = 'doctrine_mongodb.odm.document_manager';
                }
                if(!$currentManagerConfig['schema_tool']) {
                    $currentManagerConfig['schema_tool'] = 'h4cc_alice_fixtures.orm.schema_tool.mongodb';
                }
                break;
            default:
                throw new \InvalidArgumentException("Invalid value for 'doctrine'");
        }

        $container->setAlias('h4cc_alice_fixtures.object_manager', $currentManagerConfig['object_manager']);
        $container->setAlias('h4cc_alice_fixtures.orm.schema_tool', $currentManagerConfig['schema_tool']);

        $managerConfig = array(
            'locale' => $currentManagerConfig['locale'],
            'seed' => $currentManagerConfig['seed'],
            'do_flush' => $currentManagerConfig['do_flush'],
        );
        $container->getDefinition('h4cc_alice_fixtures.manager')->replaceArgument(0, $managerConfig);
    }
}
