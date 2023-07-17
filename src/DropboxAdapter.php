<?php

namespace Spatie\FlysystemDropbox;

use Generator;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;

class DropboxAdapter implements FilesystemAdapter, ChecksumProvider
{
    protected Client $client;

    protected PathPrefixer $prefixer;

    protected MimeTypeDetector $mimeTypeDetector;

    public function __construct(
        Client $client,
        string $prefix = '',
        MimeTypeDetector $mimeTypeDetector = null
    ) {
        $this->client = $client;
        $this->prefixer = new PathPrefixer($prefix);
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
            $meta = $this->client->getMetadata($location);

            return $meta['.tag'] === 'file';
        } catch (BadRequest) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        $location = $this->applyPathPrefix($path);

        try {
            $meta = $this->client->getMetadata($location);

            return $meta['.tag'] === 'folder';
        } catch (BadRequest) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->upload($location, $contents, 'overwrite');
        } catch (BadRequest $exception) {
            throw UnableToWriteFile::atLocation($location, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->upload($location, $contents, 'overwrite');
        } catch (BadRequest $exception) {
            throw UnableToWriteFile::atLocation($location, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function read(string $path): string
    {
        $object = $this->readStream($path);

        $contents = stream_get_contents($object);
        fclose($object);

        unset($object);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function readStream(string $path)
    {
        $location = $this->applyPathPrefix($path);

        try {
            $stream = $this->client->download($location);
        } catch (BadRequest $exception) {
            throw UnableToReadFile::fromLocation($location, $exception->getMessage(), $exception);
        }

        return $stream;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->delete($location);
        } catch (BadRequest $exception) {
            throw UnableToDeleteFile::atLocation($location, $exception->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory(string $path): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->delete($location);
        } catch (UnableToDeleteFile $exception) {
            throw UnableToDeleteDirectory::atLocation($location, $exception->getPrevious()->getMessage(), $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createDirectory(string $path, Config $config): void
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->createFolder($location);
        } catch (BadRequest $exception) {
            throw UnableToCreateDirectory::atLocation($location, $exception->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Adapter does not support visibility controls.');
    }

    /**
     * {@inheritDoc}
     */
    public function visibility(string $path): FileAttributes
    {
        // Noop
        return new FileAttributes($path);
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function lastModified(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        try {
            $response = $this->client->getMetadata($location);
        } catch (BadRequest $exception) {
            throw UnableToRetrieveMetadata::lastModified($location, $exception->getMessage());
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
     * {@inheritDoc}
     */
    public function checksum(string $path, Config $config): string
    {
        $algo = $config->get('checksum_algo', 'sha256');
        $location = $this->applyPathPrefix($path);

        try {
            $response = $this->client->getMetadata($location);
        } catch (BadRequest $exception) {
            throw new UnableToProvideChecksum(
                reason: 'Unable to retrieve metadata.',
                path: $path,
                previous: $exception,
            );
        }

        if (empty($response['content_hash'])) {
            throw new UnableToProvideChecksum(
                reason: 'Content-Hash not provided by Dropbox metadata.',
                path: $path,
            );
        }

        return $algo === 'sha256'
            ? $response['content_hash']
            : hash($algo, $response['content_hash']);
    }

    /**
     * {@inheritDoc}
     */
    public function fileSize(string $path): FileAttributes
    {
        $location = $this->applyPathPrefix($path);

        try {
            $response = $this->client->getMetadata($location);
        } catch (BadRequest $exception) {
            throw UnableToRetrieveMetadata::lastModified($location, $exception->getMessage());
        }

        return new FileAttributes(
            $path,
            $response['size'] ?? null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function listContents(string $path = '', bool $deep = false): iterable
    {
        foreach ($this->iterateFolderContents($path, $deep) as $entry) {
            $storageAttrs = $this->normalizeResponse($entry);

            // Avoid including the base directory itself
            if ($storageAttrs->isDir() && $storageAttrs->path() === $path) {
                continue;
            }

            yield $storageAttrs;
        }
    }

    protected function iterateFolderContents(string $path = '', bool $deep = false): Generator
    {
        $location = $this->applyPathPrefix($path);

        try {
            $result = $this->client->listFolder($location, $deep);
        } catch (BadRequest $exception) {
            return;
        }

        yield from $result['entries'];

        while ($result['has_more']) {
            $result = $this->client->listFolderContinue($result['cursor']);
            yield from $result['entries'];
        }
    }

    protected function normalizeResponse(array $response): StorageAttributes
    {
        $timestamp = (isset($response['server_modified'])) ? strtotime($response['server_modified']) : null;

        if ($response['.tag'] === 'folder') {
            $normalizedPath = ltrim($this->prefixer->stripDirectoryPrefix($response['path_display']), '/');

            return new DirectoryAttributes(
                $normalizedPath,
                null,
                $timestamp
            );
        }

        $normalizedPath = ltrim($this->prefixer->stripPrefix($response['path_display']), '/');

        return new FileAttributes(
            $normalizedPath,
            $response['size'] ?? null,
            null,
            $timestamp,
            $this->mimeTypeDetector->detectMimeTypeFromPath($normalizedPath)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $path = $this->applyPathPrefix($source);
        $newPath = $this->applyPathPrefix($destination);

        try {
            $this->client->move($path, $newPath);
        } catch (BadRequest $exception) {
            throw UnableToMoveFile::fromLocationTo($path, $newPath, $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $path = $this->applyPathPrefix($source);
        $newPath = $this->applyPathPrefix($destination);

        try {
            $this->client->copy($path, $newPath);
        } catch (BadRequest $e) {
            throw UnableToCopyFile::fromLocationTo($path, $newPath, $e);
        }
    }

    protected function applyPathPrefix($path): string
    {
        return '/'.trim($this->prefixer->prefixPath($path), '/');
    }

    public function getUrl(string $path): string
    {
        return $this->client->getTemporaryLink($path);
    }
}
