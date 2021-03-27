<?php

namespace Spatie\FlysystemDropbox;

use League\Flysystem;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;

class DropboxAdapter implements Flysystem\FilesystemAdapter
{
    /** @var Client */
    protected $client;

    /** @var Flysystem\PathPrefixer */
    protected $prefixer;

    /** @var MimeTypeDetector */
    protected $mimeTypeDetector;

    public function __construct(
        Client $client,
        string $prefix = '',
        MimeTypeDetector $mimeTypeDetector = null
    ) {
        $this->client = $client;
        $this->prefixer = new Flysystem\PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector ?: new FinfoMimeTypeDetector();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function fileExists(string $path): bool
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->getMetadata($location);
            return true;
        } catch (BadRequest $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, Flysystem\Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->upload($path, $contents, 'overwrite');
        } catch (BadRequest $e) {
            throw Flysystem\UnableToWriteFile::atLocation($location, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Flysystem\Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->upload($path, $contents, 'overwrite');
        } catch (BadRequest $e) {
            throw Flysystem\UnableToWriteFile::atLocation($location, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        if (! $object = $this->readStream($path)) {
            throw Flysystem\UnableToReadFile::fromLocation($path);
        }

        $contents = stream_get_contents($object['stream']);
        fclose($object['stream']);
        unset($object['stream']);

        if ($contents === false) {
            throw Flysystem\UnableToReadFile::fromLocation($path);
        }

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $stream = $this->client->download($path);
        } catch (BadRequest $e) {
            return false;
        }

        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->delete($location);
        } catch (BadRequest $e) {
            throw Flysystem\UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $this->delete($path);
        } catch(Flysystem\UnableToDeleteFile $e) {
            throw Flysystem\UnableToDeleteDirectory::atLocation($path, $e->getPrevious()->getMessage(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Flysystem\Config $config): void
    {
        $path = $this->applyPathPrefix($path);

        try {
            $this->client->createFolder($path);
        } catch (BadRequest $e) {
            throw Flysystem\UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw Flysystem\UnableToSetVisibility::atLocation($path, 'Adapter does not support visibility controls.');
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): FileAttributes
    {
        // Noop
        return new FileAttributes($path);
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes(
            $path,
            null,
            null,
            null,
            $this->mimeTypeDetector->detectMimeTypeFromPath($path)
        );
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        try {
            $response = $this->client->getMetadata($location);
        } catch (BadRequest $e) {
            throw Flysystem\UnableToRetrieveMetadata::lastModified($location, $e->getMessage());
        }

        $timestamp = (isset($response['server_modified'])) ? strtotime($response['server_modified']) : null;

        return new FileAttributes(
            $path,
            null,
            null,
            $timestamp
        );
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        try {
            $response = $this->client->getMetadata($location);
        } catch (BadRequest $e) {
            throw Flysystem\UnableToRetrieveMetadata::lastModified($location, $e->getMessage());
        }

        return new FileAttributes(
            $path,
            $response['size'] ?? null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function listContents(string $path = '', bool $deep = false): array
    {
        $location = $this->applyPathPrefix($path);

        try {
            $result = $this->client->listFolder($location, $path);
        } catch (BadRequest $e) {
            return [];
        }

        $entries = $result['entries'];
        while ($result['has_more']) {
            $result = $this->client->listFolderContinue($result['cursor']);
            $entries = array_merge($entries, $result['entries']);
        }

        if (!count($entries)) {
            return [];
        }

        return array_map(function ($entry) {
            return $this->normalizeResponse($entry);
        }, $entries);
    }

    protected function normalizeResponse(array $response): Flysystem\StorageAttributes
    {
        $timestamp = (isset($response['server_modified'])) ? strtotime($response['server_modified']) : null;

        if ($response['.tag'] === 'folder') {
            $normalizedPath = $this->prefixer->stripDirectoryPrefix($response['path_display']);

            return new Flysystem\DirectoryAttributes(
                $normalizedPath,
                null,
                $timestamp
            );
        }

        $normalizedPath = $this->prefixer->stripPrefix($response['path_display']);

        return new Flysystem\FileAttributes(
            $normalizedPath,
            $response['size'] ?? null,
            null,
            $timestamp,
            $this->mimeTypeDetector->detectMimeTypeFromPath($normalizedPath)
        );
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $path = $this->applyPathPrefix($source);
        $newPath = $this->applyPathPrefix($destination);

        try {
            $this->client->move($path, $newPath);
        } catch (BadRequest $e) {
            throw Flysystem\UnableToMoveFile::fromLocationTo($path, $newPath, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $path = $this->applyPathPrefix($source);
        $newPath = $this->applyPathPrefix($destination);

        try {
            $this->client->copy($path, $newPath);
        } catch (BadRequest $e) {
            throw Flysystem\UnableToCopyFile::fromLocationTo($path, $newPath, $e);
        }
    }

    protected function applyPathPrefix($path): string
    {
        return '/'.trim($this->prefixer->prefixPath($path), '/');
    }
}
