<?php

namespace Micro\TraeffikGenericBundle;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * TraeffikGenericBundle.
 */
class MicroTraeffikGenericBundle extends Bundle
{
    /**
     * @return array<class-string>
     */
    public static function getBundleDependencies(KernelInterface $kernel): array
    {
        return [
            FrameworkBundle::class,
        ];
    }
}
