<?php

declare(strict_types=1);

namespace App\Command;

use App\Console\Lock\LockableInterface;
use App\Console\Lock\LockableTrait;
use App\Entity\Channel;
use App\Entity\Video;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\UrlHelper;
use YouTube\YouTubeDownloader;

use function json_decode;

class ObserverCommand extends Command implements LockableInterface
{
    use LockableTrait;

    protected static $defaultName = 'regular:check_fresh_video';

    private YouTubeDownloader $youTubeDownloader;
    private Client $client;
    private EntityManagerInterface $entityManager;
    private UrlHelper $urlHelper;
    private string $kernelPublicPath;
    private string $projectDomain;

    /**
     * @required
     *
     * @param YouTubeDownloader $youTubeDownloader
     * @param Client $client
     * @param EntityManagerInterface $entityManager
     * @param UrlHelper $urlHelper
     * @param string $kernelPublicPath
     * @param string $projectDomain
     */
    public function dependencyInjection(
        YouTubeDownloader $youTubeDownloader,
        Client $client,
        EntityManagerInterface $entityManager,
        UrlHelper $urlHelper,
        string $kernelPublicPath,
        string $projectDomain
    ): void {
        $this->youTubeDownloader = $youTubeDownloader;
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->urlHelper = $urlHelper;
        $this->kernelPublicPath = $kernelPublicPath;
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

        $videoIdList = [];
        $existExternalIdList = [];

        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) ';
        $userAgent .= 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.97 Safari/537.36';

        $channelRepository = $this->entityManager->getRepository(Channel::class);
        $channelList = $channelRepository->findAll();

        /** @var Channel[] $channelList */
        foreach ($channelList as $channel) {
            $response = $this->client->get($channel->getLink(), [
                RequestOptions::HEADERS => [
                    'user-agent' => $userAgent,
                ],
            ]);
            $html = $response->getBody()->getContents();

            preg_match_all('/window\["ytInitialData"] = ({.+})/m', $html, $matches);

            $jsonContent = $matches[1][0] ?? null;

            $content = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

            $tabs = $content['contents']['twoColumnBrowseResultsRenderer']['tabs'];
            $sectionListRenderer = $tabs[1]['tabRenderer']['content']['sectionListRenderer']['contents'];
            $videoInfoList = $sectionListRenderer[0]['itemSectionRenderer']['contents'][0]['gridRenderer']['items'];

            $videoRepository = $this->entityManager->getRepository(Video::class);
            $videoList = $videoRepository->findAll();

            /** @var Video[] $videoList */
            foreach ($videoList as $video) {
                $existExternalIdList[] = $video->getExternalId();
            }

            foreach ($videoInfoList as $videoInfo) {
                if (isset($videoInfo['gridVideoRenderer']['viewCountText']['runs'])) {
                    continue;
                }

                $videoIdList[] = $videoInfo['gridVideoRenderer']['videoId'];
            }

            $freshVideoIdList = array_diff($videoIdList, $existExternalIdList);

            foreach ($freshVideoIdList as $freshVideoId) {
                $downloadLinkList = $this->youTubeDownloader->getDownloadLinks(
                    "https://www.youtube.com/watch?v=$freshVideoId"
                );

                $video = new Video();
                $video->setChannel($channel);
                $video->setExternalId($freshVideoId);
                $video->setDownloadLinks($downloadLinkList);
                $video->setPublicUrl("/$freshVideoId.mp4");
                $video->setCreatedAt(new DateTime());
                $video->setUpdatedAt(new DateTime());

                $downloadUrl = $this->searchTopUrl($downloadLinkList);

                $output->writeln("Start download $downloadUrl");

                if (!$downloadUrl) {
                    continue;
                }

                $pathname = "{$this->kernelPublicPath}/video/$freshVideoId.mp4";
                $this->downloadFile($downloadUrl, $pathname);

                $this->entityManager->persist($video);
            }
        }

        $this->entityManager->flush();

        return self::SUCCESS;
    }

    /**
     * @param $downloadLinkList
     *
     * @return string|null
     */
    private function searchTopUrl($downloadLinkList): ?string
    {
        foreach ($downloadLinkList as $downloadLink) {
            $isPrettyDownloadUrl = strpos($downloadLink['format'], 'video') !== false
                && strpos($downloadLink['format'], 'audio') !== false
                && strpos($downloadLink['format'], '1080p') !== false
            ;

            if ($isPrettyDownloadUrl) {
                return $downloadLink['url'];
            }
        }

        foreach ($downloadLinkList as $downloadLink) {
            $isPrettyDownloadUrl = strpos($downloadLink['format'], 'video') !== false
                && strpos($downloadLink['format'], 'audio') !== false
                && strpos($downloadLink['format'], '720p') !== false
            ;

            if ($isPrettyDownloadUrl) {
                return $downloadLink['url'];
            }
        }

        foreach ($downloadLinkList as $downloadLink) {
            $isPrettyDownloadUrl = strpos($downloadLink['format'], 'video') !== false
                && strpos($downloadLink['format'], 'audio') !== false
                && strpos($downloadLink['format'], '480p') !== false
            ;

            if ($isPrettyDownloadUrl) {
                return $downloadLink['url'];
            }
        }

        foreach ($downloadLinkList as $downloadLink) {
            $isPrettyDownloadUrl = strpos($downloadLink['format'], 'video') !== false
                && strpos($downloadLink['format'], 'audio') !== false
                && strpos($downloadLink['format'], '360p') !== false
            ;

            if ($isPrettyDownloadUrl) {
                return $downloadLink['url'];
            }
        }

        foreach ($downloadLinkList as $downloadLink) {
            $isPrettyDownloadUrl = strpos($downloadLink['format'], 'video') !== false
                && strpos($downloadLink['format'], 'audio') !== false
                && strpos($downloadLink['format'], '240p') !== false
            ;

            if ($isPrettyDownloadUrl) {
                return $downloadLink['url'];
            }
        }

        return null;
    }

    /**
     * @param string $url
     * @param string $path
     */
    private function downloadFile(string $url, string $path): void
    {
        $newName = $path;
        $file = fopen($url, 'rb');

        $newFile = fopen($newName, 'wb');

        while (!feof($file)) {
            fwrite($newFile, fread($file, 1024 * 8), 1024 * 8);
        }

        fclose($file);
        fclose($newFile);
    }
}
