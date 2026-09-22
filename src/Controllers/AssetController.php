<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Serves the panel's CSS/JS/fonts straight from the package, so the host app
 * never needs a publish step or a copy under its own public/ folder.
 */
class AssetController extends BaseController
{
    private const CONTENT_TYPES = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2'];

    /**
     * A nested path arrives as one segment or several depending on the
     * host's Config\Routing::$multipleSegmentsOneParam, so it takes
     * whatever the router hands over and rejoins it.
     */
    public function serve(string ...$segments): ResponseInterface
    {
        $path = implode('/', $segments);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! isset(self::CONTENT_TYPES[$extension])) {
            return $this->response->setStatusCode(404);
        }

        $root = realpath(__DIR__ . '/../Assets');
        $file = realpath(($root === false ? '' : $root) . '/' . $path);

        if ($root === false || $file === false || ! str_starts_with($file, $root) || ! is_file($file)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response
            ->setContentType(self::CONTENT_TYPES[$extension])
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(file_get_contents($file));
    }
}
