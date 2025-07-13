<?php

namespace Micro\TraeffikGenericBundle\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use UnitEnum;

/**
 * MicroTraeffikGenericExtension.
 *
 * @psalm-type ParamConfig array<int|string,array<mixed>|bool|float|int|string|UnitEnum|null>
 */
class MicroTraeffikGenericExtension extends Extension
{
    /**
     * @param array<int|string,mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $this->addParam($config, $container, $this->getAlias());
    }

    /**
     * @param array<int|string,mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function getAlias(): string
    {
        return 'micro_traeffik_generic';
    }

    /**
     * @param ParamConfig $config
     */
    public function addParam(array $config, ContainerBuilder $container, string $prefix): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                /** @psalm-var ParamConfig */
                $innerArray = $value;
                $this->addParam($innerArray, $container, $prefix . '.' . $key);
                continue;
            }
            $container->setParameter($prefix . '.' . $key, $value);
        }
    }
}
