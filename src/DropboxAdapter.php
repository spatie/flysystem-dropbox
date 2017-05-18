<?php

namespace Spatie\FlysystemDropbox;

use LogicException;
use Spatie\Dropbox\Client;
use League\Flysystem\Config;
use Spatie\Dropbox\Exceptions\BadRequest;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;

class DropboxAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /** @var \Spatie\Dropbox\Client */
    protected $client;

    public function __construct(Client $client, string $prefix = '')
    {
        $this->client = $client;

        $this->setPathPrefix($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, 'add');
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, 'add');
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, 'overwrite');
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, 'overwrite');
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newPath): bool
    {
        $path = $this->applyPathPrefix($path);
        $newPath = $this->applyPathPrefix($newPath);

        try {
            $this->client->move($path, $newPath);
        } catch (BadRequest $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath): bool
    {
        $path = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        try {
            $this->client->copy($path, $newpath);
        } catch (BadRequest $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path): bool
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->delete($location);
        } catch (BadRequest $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname): bool
    {
        return $this->delete($dirname);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        $path = $this->applyPathPrefix($dirname);

        try {
            $object = $this->client->createFolder($path);
        } catch (BadRequest $e) {
            return false;
        }

        return $this->normalizeResponse($object);
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        if (! $object = $this->readStream($path)) {
            return false;
        }

        $object['contents'] = stream_get_contents($object['stream']);
        fclose($object['stream']);
        unset($object['stream']);

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $stream = $this->client->download($path);
        } catch (BadRequest $e) {
            return false;
        }

        return compact('stream');
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $location = $this->applyPathPrefix($directory);

        $result = $this->client->listFolder($location, $recursive);

        if (! count($result['entries'])) {
            return [];
        }

        $cleanedPathDisplay = $this->getCleanedPathDisplay($result['entries']);

        return array_map(function ($entry) use ($cleanedPathDisplay) {
            $path = $this->removePathPrefix($entry['path_display']);

            // use cleaned path display to fix path case
            foreach ($cleanedPathDisplay as $pathLower => $pathDisplay) {
                $path = preg_replace('/^'.preg_quote($pathLower, '/').'/i', $pathDisplay, $path);
            }

            $entry['path_display'] = $path;

            return $this->normalizeResponse($entry);
        }, $result['entries']);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->getMetadata($path);
        } catch (BadRequest $e) {
            return false;
        }

        return $this->normalizeResponse($object);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        throw new LogicException("The Dropbox API v2 does not support mimetypes. Given path: `{$path}`.");
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getTemporaryLink(string $path): string
    {
        return $this->client->getTemporaryLink($path);
    }

    public function getThumbnail(string $path, string $format = 'jpeg', string $size = 'w64h64')
    {
        return $this->client->getThumbnail($path, $format, $size);
    }

    /**
     * {@inheritdoc}
     */
    public function applyPathPrefix($path): string
    {
        $path = parent::applyPathPrefix($path);

        return '/'.trim($path, '/');
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param string $path
     * @param resource|string $contents
     * @param string $mode
     *
     * @return array|false file metadata
     */
    protected function upload(string $path, $contents, string $mode)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->upload($path, $contents, $mode);
        } catch (BadRequest $e) {
            return false;
        }

        return $this->normalizeResponse($object);
    }

    protected function normalizeResponse(array $response): array
    {
        $normalizedPath = ltrim($this->removePathPrefix($response['path_display']), '/');

        $normalizedResponse = ['path' => $normalizedPath];

        if (isset($response['server_modified'])) {
            $normalizedResponse['timestamp'] = strtotime($response['server_modified']);
        }

        if (isset($response['size'])) {
            $normalizedResponse['size'] = $response['size'];
            $normalizedResponse['bytes'] = $response['size'];
        }

        $type = ($response['.tag'] === 'folder' ? 'dir' : 'file');
        $normalizedResponse['type'] = $type;

        return $normalizedResponse;
    }

    protected function getCleanedPathDisplay($entries)
    {
        // init temp associative array that will contains
        // path lower as key and path display as value
        $cleanedPathDisplay = [];
        foreach ($entries as $entry) {
            // we only need folder paths
            if ($entry['.tag'] === 'folder') {
                // add this folder path association to temp array
                $cleanedPathDisplay[$entry['path_lower']] = $entry['path_display'];

                // search for parent cleaned path display
                $cleanedPathDisplay = $this->addParentsToCleanedPathDisplay($entry, $cleanedPathDisplay);
            }
        }

        // reverse to get deep paths first
        $cleanedPathDisplay = array_reverse($cleanedPathDisplay);

        return $cleanedPathDisplay;
    }

    protected function addParentsToCleanedPathDisplay($entry, $cleanedPathDisplay)
    {
        // try to find parent paths that we did not know
        $pathParts = explode('/', $entry['path_lower']);
        do {
            // up to parent
            array_pop($pathParts);
            $parentPathLower = implode('/', $pathParts);

            // if we did not know this path
            // get the path display from Dropbox and add it to temp assoc array
            if (! array_key_exists($parentPathLower, $cleanedPathDisplay)) {
                $prefixedPath = $this->applyPathPrefix($parentPathLower);
                $metadata = $this->client->getMetadata($prefixedPath);
                $cleanedPathDisplay = array_merge(
                    [$metadata['path_lower'] => $metadata['path_display']],
                    $cleanedPathDisplay
                );
            } else {
                // if this path is known, parents will do too
                // so we can stop this loop by emptying path parts
                $pathParts = [];
            }
        } while (count($pathParts) > 2);

        return $cleanedPathDisplay;
    }
}
