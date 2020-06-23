<?php

declare(strict_types=1);

namespace App\Console\Lock;

interface LockableInterface
{
    /**
     * @param string $lockPrefix
     */
    public function setLockPrefix(string $lockPrefix): void;

    /**
     * @return string
     */
    public function getLockPrefix(): string;
}
