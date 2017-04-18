<?php

namespace Spatie\FlysystemDropbox;


use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use Exception;

class DropboxAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /** @var \Spatie\FlysystemDropbox\DropboxClient */
    protected $client;

    public function __construct(DropboxClient $client, string $prefix = null)
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
        // TODO: Implement writeStream() method.
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
        // TODO: Implement updateStream() method.
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
        // TODO: Implement read() method.
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        // TODO: Implement readStream() method.
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $listing = [];

        $location = $this->applyPathPrefix($directory);

        $result = $this->client->listContents($location, $recursive);

        if (!count($result['entries'])) {
            return [];
        }

        foreach ($result['entries'] as $object) {
            $path = $this->removePathPrefix($object['path_display']);
            $listing[] = $this->normalizeResponse($object, $path);
        }

        return $listing;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->getMetadata($path);
        } catch(Exception $e) {
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
        throw new LogicException('The Dropbox API v2 does not support mimetypes. Path: ' . $path);
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

        return '/' . ltrim(rtrim($path, '/'), '/');
    }

    public function getClient(): DropboxClient
    {
        return $this->client;
    }

    protected function upload($path, $contents, $mode)
    {
        $location = $this->applyPathPrefix($path);

        $object = $this->client->uploadFromString($location, $mode, $contents);

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

        $result['type'] = $response['.tag'] === 'folder' ? 'dir' : 'file';

        return $result;
    }
}
