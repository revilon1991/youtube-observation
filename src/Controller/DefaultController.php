<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use YouTube\YouTubeDownloader;
use YouTube\YoutubeStreamer;

class DefaultController extends AbstractController
{
    /**
     * @Route(path="/")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function index(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('admin');
    }

    /**
     * @Route(path="/download")
     *
     * @return Response
     */
    public function download(): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        $content = file_get_contents("$projectDir/vendor/athlon1600/youtube-downloader/public/index.html");
        $content = str_replace(['video_info.php', 'stream.php'], ['video_info', 'stream'], $content);

        return new Response($content);
    }

    /**
     * @Route(path="/video_info")
     *
     * @param Request $request
     * @param YouTubeDownloader $youTubeDownloader
     *
     * @return JsonResponse
     */
    public function videoInfo(Request $request, YouTubeDownloader $youTubeDownloader): JsonResponse
    {
        $url = $request->query->get('url');

        if (!$url) {
            throw new BadRequestHttpException();
        }

        $links = $youTubeDownloader->getDownloadLinks($url);
        $error = $youTubeDownloader->getLastError();

        return $this->json([
            'links' => $links,
            'error' => $error,
        ]);
    }

    /**
     * @Route(path="/stream")
     *
     * @param Request $request
     * @param YoutubeStreamer $youtubeStreamer
     */
    public function streamVideo(Request $request, YoutubeStreamer $youtubeStreamer): void
    {
        set_time_limit(0);

        $url = $request->query->get('url');

        if (!$url) {
            throw new BadRequestHttpException();
        }

        $youtubeStreamer->stream($url);
    }
}
