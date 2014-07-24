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
use Symfony\Component\DependencyInjection\Definition;
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

        $this->loadManagersServices($config, $container);
    }

    private function loadManagersServices(array $config, ContainerBuilder $container)
    {
        if (empty($config['default_manager'])) {
            $keys = array_keys($config['managers']);
            $config['default_manager'] = reset($keys);
        }

        foreach($config['managers'] as $name => $currentManagerConfig) {
            if(!in_array($currentManagerConfig['doctrine'], ['orm', 'mongodb-odm'])) {
                throw new \InvalidArgumentException("Invalid value for 'doctrine'");
            }

            $managerConfig = array(
                'locale' => $currentManagerConfig['locale'],
                'seed' => $currentManagerConfig['seed'],
                'do_flush' => $currentManagerConfig['do_flush'],
            );

            $schemaToolServiceId = $this->getSchemaToolServiceIdForCurrentConfig($currentManagerConfig, $container);

            $managerServiceDefinition = new Definition();
            $managerServiceDefinition->setClass('%h4cc_alice_fixtures.manager.class%');
            $managerServiceDefinition->setArguments([
                $managerConfig,
                new Reference($this->getObjectManagerServiceIdForCurrentConfig($currentManagerConfig, $container)),
                new Reference('h4cc_alice_fixtures.loader.factory'),
                new Reference($schemaToolServiceId)
            ]);
            // set manager
            $container->setDefinition(sprintf('h4cc_alice_fixtures.%s_manager', $name), $managerServiceDefinition);

            // set a alias schema tool service to ease find by manager name
            $container->setAlias(sprintf('h4cc_alice_fixtures.schema_tool_%s', $name), $schemaToolServiceId);
        }
        // set default alias manager
        $container->setAlias('h4cc_alice_fixtures.manager', sprintf('h4cc_alice_fixtures.%s_manager', $config['default_manager']));
    }

    private function getObjectManagerServiceIdForCurrentConfig(array $currentManagerConfig, ContainerBuilder $container)
    {
        if(isset($currentManagerConfig['object_manager'])) {
            $serviceId = $currentManagerConfig['object_manager'];
        } else {
            $serviceId = sprintf('h4cc_alice_fixtures.object_manager.%s', $this->getCleanDoctrineConfigName($currentManagerConfig['doctrine']));

            if (!$container->has($serviceId)) {
                $container->setAlias($serviceId, $this->getDefaultManagerServiceId($currentManagerConfig['doctrine']));
            }
        }

        return $serviceId;
    }

    private function getSchemaToolServiceIdForCurrentConfig(array $currentManagerConfig, ContainerBuilder $container)
    {
        $serviceId = sprintf('h4cc_alice_fixtures.schema_tool.%s', $this->getCleanDoctrineConfigName($currentManagerConfig['doctrine']));

        if (!$container->has($serviceId)) {
            $schemaToolDefinition = new Definition();
            $schemaToolDefinition->setClass($this->getSchemaToolClass($currentManagerConfig['doctrine']));
            $schemaToolDefinition->setArguments([
                new Reference($this->getObjectManagerServiceIdForCurrentConfig($currentManagerConfig, $container))
            ]);
            $container->setDefinition($serviceId, $schemaToolDefinition);
        }

        return $serviceId;
    }

    private function getDefaultManagerServiceId($doctrineConfigName)
    {
        $defaultManagerServiceId = null;

        switch($doctrineConfigName) {
            case 'orm':
                $defaultManagerServiceId = 'doctrine.orm.entity_manager';
                break;
            case 'mongodb-odm':
                $defaultManagerServiceId = 'doctrine_mongodb.odm.document_manager';
                break;
        }

        return $defaultManagerServiceId;
    }

    private function getSchemaToolClass($doctrineConfigName)
    {
        $defaultSchemaToolClass = null;

        switch($doctrineConfigName) {
            case 'orm':
                $defaultSchemaToolClass = '%h4cc_alice_fixtures.orm.schema_tool.doctrine.class%';
                break;
            case 'mongodb-odm':
                $defaultSchemaToolClass = '%h4cc_alice_fixtures.orm.schema_tool.mongodb.class%';
                break;
        }

        return $defaultSchemaToolClass;
    }

    private function getCleanDoctrineConfigName($doctrineConfigName)
    {
        $cleanDoctrineConfigName = null;

        switch($doctrineConfigName) {
            case 'orm':
                $cleanDoctrineConfigName = 'orm';
                break;
            case 'mongodb-odm':
                $cleanDoctrineConfigName = 'mongodb_odm';
                break;
        }

        return $cleanDoctrineConfigName;
    }
}