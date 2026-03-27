<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        // Pushes cache to the container's native filesystem (lightning fast)
        return '/tmp/symfony/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        // Pushes logs to the container's native filesystem (bypasses permissions)
        return '/tmp/symfony/log';
    }
}
