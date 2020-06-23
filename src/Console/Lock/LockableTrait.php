<?php

declare(strict_types=1);

namespace App\Console\Lock;

use Symfony\Component\Console\Command\LockableTrait as SymfonyLockableTrait;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * @method getName(): string
 */
trait LockableTrait
{
    use SymfonyLockableTrait;

    /**
     * @var string
     */
    private $lockPrefix;

    /**
     * @param null $name
     * @param bool $blocking
     *
     * @return bool
     */
    private function lock($name = null, $blocking = false)
    {
        $resourceName = $name ?: sprintf('%s:%s', $this->getLockPrefix(), $this->getName());

        if (!class_exists(FlockStore::class)) {
            throw new LogicException('To enable the locking feature you must install the symfony/lock component.');
        }

        if (null !== $this->lock) {
            throw new LogicException('A lock is already in place.');
        }

        $store = new FlockStore();

        $this->lock = (new LockFactory($store))->createLock($resourceName ?: $this->getName());

        if (!$this->lock->acquire($blocking)) {
            $this->lock = null;

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setLockPrefix(string $lockPrefix): void
    {
        $this->lockPrefix = $lockPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getLockPrefix(): string
    {
        return $this->lockPrefix;
    }
}
