<?php

declare(strict_types=1);

namespace App\Command;

use App\Console\Lock\LockableInterface;
use App\Console\Lock\LockableTrait;
use App\Entity\Video;
use App\Service\CdnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class MoveVideoToCdnCommand extends Command implements LockableInterface
{
    use LockableTrait;

    protected static $defaultName = 'regular:move_video_to_cdn';

    private CdnService $cdnService;
    private string $kernelPublicPath;
    private EntityManagerInterface $entityManager;
    private string $projectDomain;

    /**
     * @required
     *
     * @param CdnService $cdnService
     * @param string $kernelPublicPath
     * @param EntityManagerInterface $entityManager
     * @param string $projectDomain
     */
    public function dependencyInjection(
        CdnService $cdnService,
        string $kernelPublicPath,
        EntityManagerInterface $entityManager,
        string $projectDomain
    ): void {
        $this->cdnService = $cdnService;
        $this->kernelPublicPath = $kernelPublicPath;
        $this->entityManager = $entityManager;
        $this->projectDomain = $projectDomain;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setLockPrefix($this->projectDomain);

        if (!$this->lock()) {
            throw new RuntimeException('The command is already running in another process.');
        }

        $videoRepository = $this->entityManager->getRepository(Video::class);

        $finder = new Finder();

        $finderDirectory = $finder->in("{$this->kernelPublicPath}/video");

        /** @var SplFileInfo $fileInfo */
        foreach ($finderDirectory->files()->getIterator() as $fileInfo) {
            $sourcePathname = $fileInfo->getPathname();
            $filenameWithoutExtension = $fileInfo->getFilenameWithoutExtension();
            $extension = $fileInfo->getExtension();
            $filename = "$filenameWithoutExtension.$extension";

            $targetPathname = $this->getMediaFilePath($filename, '.');

            try {
                $this->cdnService->uploadFile($sourcePathname, $targetPathname);

                /** @var Video $video */
                $video = $videoRepository->findOneBy(['externalId' => $filenameWithoutExtension]);
                $publicPath = '/cdn' . ltrim($targetPathname, '.');
                $video->setPublicUrl($publicPath);

                $this->entityManager->flush();
            } catch (Exception $exception) {
                if ($this->cdnService->isFileExist($targetPathname)) {
                    $this->cdnService->deleteFile($targetPathname);
                }

                continue;
            }

            unlink($sourcePathname);
        }

        return self::SUCCESS;
    }

    /**
     * @param string $filename
     * @param string $uploadCdnDir
     *
     * @return string
     */
    protected function getMediaFilePath(string $filename, string $uploadCdnDir): string
    {
        $prefixFirst = substr($filename, 0, 2);
        $prefixSecond = substr($filename, 2, 2);

        $filePath = sprintf('%s/%s/%s', $prefixFirst, $prefixSecond, $filename);
        $filePath = sprintf('%s/%s', $uploadCdnDir, $filePath);

        return $filePath;
    }
}
