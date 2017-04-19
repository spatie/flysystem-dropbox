<?php

namespace Spatie\FlysystemDropbox;

use Exception;
use LogicException;
use Spatie\Dropbox\Client;
use League\Flysystem\Config;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;

class DropboxAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /** @var \Spatie\FlysystemDropbox\DropboxClient */
    protected $client;

    public function __construct(Client $client, string $prefix = null)
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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

        $object = $this->client->createFolder($path);

        if ($object === null) {
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

        $stream = $this->client->getFile($path);

        return compact('stream');
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $location = $this->applyPathPrefix($directory);

        $result = $this->client->listContents($location, $recursive);

        if (! count($result['entries'])) {
            return [];
        }

        return array_map(function ($entry) {
            $path = $this->removePathPrefix($entry['path_display']);

            return $this->normalizeResponse($entry, $path);
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
        } catch (Exception $e) {
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

        return '/'.ltrim(rtrim($path, '/'), '/');
    }

    public function getClient(): DropboxClient
    {
        return $this->client;
    }

    /**
     * @param string           $path
     * @param resource|string  $contents
     * @param string           $mode
     *
     * @return array|false file metadata
     */
    protected function upload(string $path, $contents, string $mode)
    {
        $path = $this->applyPathPrefix($path);

        $object = $this->client->upload($path, $mode, $contents);

        return $this->normalizeResponse($object);
    }

    protected function normalizeResponse(array $response): array
    {
        $result = ['path' => ltrim($this->removePathPrefix($response['path_display']), '/')];

        if (isset($response['server_modified'])) {
            $result['timestamp'] = strtotime($response['server_modified']);
        }

        if (isset($response['size'])) {
            $result['bytes'] = $response['size'];
        }

        $result['type'] = $response['.tag'] === 'folder'
            ? 'dir'
            : 'file';

        return $result;
    }
}
