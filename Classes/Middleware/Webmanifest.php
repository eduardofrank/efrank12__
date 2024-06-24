<?php

namespace EduardoFrank\Efrank12\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class Webmanifest implements MiddlewareInterface
{
    protected ServerRequestInterface $request;

    final const MANIFEST_PATH = '/site.webmanifest';
    final const MANIFEST_NAME = 'site.webmanifest';

    /**
     * @throws InvalidFileException
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->request = $request;
        if ($this->isWebmanifestRequest()) {
            ob_clean();
            return (new JsonResponse())->setPayload($this->generateWebmanifest());
        }

        $this->addWebmanifestToHead();

        return $handler->handle($this->request);
    }

    protected function isWebmanifestRequest(): bool
    {
        return $this->request->getUri()->getPath() === self::MANIFEST_PATH;
    }

    protected function addWebmanifestToHead(): void
    {
        $siteUrl = $this->request->getAttribute('normalizedParams')->getSiteUrl();
        $headerData = '<link rel="manifest" href="' . $siteUrl . self::MANIFEST_NAME . '">';

        GeneralUtility::makeInstance(PageRenderer::class)->addHeaderData($headerData);
    }

    /**
     * @return array{background_color: string, display: string, icons: never[]|array<int, array{src: mixed, type: string, sizes: string}>, name?: mixed, short_name?: string, theme_color?: mixed}
     * @throws InvalidFileException
     */
    protected function generateWebmanifest(): array
    {
        $siteConfig = $this->getSiteConfig();
        $webmanifest = [
            'background_color' => '#ffffff',
            'display' => 'browser',
            'icons' => [],
        ];

        $name = $siteConfig['fullName'] ?? '';
        if (!empty($name)) {
            $webmanifest['name'] = $name;
        }

        $shortName = $siteConfig['shortName'] ?? '';
        if (!empty($shortName)) {
            $webmanifest['short_name'] = $shortName;
        }

        $themeColor = $siteConfig['themeColor'] ?? '';
        if (!empty($themeColor)) {
            $webmanifest['theme_color'] = $themeColor;
        }

        $faviconPath = $siteConfig['faviconPath'] ?? '';
        $favicon192 = $siteConfig['favicon192'] ?? '';
        $favicon512 = $siteConfig['favicon512'] ?? '';
        if (!empty($faviconPath)) {
            $icon192 = PathUtility::getPublicResourceWebPath($faviconPath . $favicon192);
            if (!empty($icon192)) {
                $webmanifest['icons'][] = ['src' => $icon192, 'type' => 'image/png', 'sizes' => '192x192'];
            }

            $icon512 = PathUtility::getPublicResourceWebPath($faviconPath . $favicon512);
            if (!empty($icon512)) {
                $webmanifest['icons'][] = ['src' => $icon512, 'type' => 'image/png', 'sizes' => '512x512'];
            }
        }

        return $webmanifest;
    }

    /**
     * @return array{fullName: ?string,shortName: ?string,themeColor: ?string,faviconPath: ?string,favicon192: ?string,favicon512: ?string}
     */
    protected function getSiteConfig(): array
    {
        /** @var Site $site */
        $site = $this->request->getAttribute('site');
        $configuration = $site->getConfiguration();
        return [
            'fullName' => $configuration['webmanifest']['full_name'] ?? null,
            'shortName' => $configuration['webmanifest']['short_name'] ?? null,
            'themeColor' => $configuration['webmanifest']['theme_color'] ?? null,
            'faviconPath' => $configuration['webmanifest']['favicon_path'] ?? null,
            'favicon192' => $configuration['webmanifest']['favicon_192'] ?? null,
            'favicon512' => $configuration['webmanifest']['favicon_512'] ?? null,
        ];
    }
}