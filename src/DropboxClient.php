<?php

namespace Spatie\FlysystemDropbox;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\StreamWrapper;
use Psr\Http\Message\StreamInterface;

class DropboxClient
{
    protected $accessToken;

    protected $client;

    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;

        $this->client = new Client([
            'base_uri' => 'https://api.dropboxapi.com/2/',
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
            ],
        ]);
    }

    public function move(string $path, string $newPath): array
    {
        $path = $this->normalizePath($path);
        $newPath = $this->normalizePath($newPath);

        $response = $this->client->post('files/move', [
            'json' => [
                'from_path' => $path,
                'to_path' => $newPath,
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function copy(string $path, string $newPath): array
    {
        $path = $this->normalizePath($path);
        $newPath = $this->normalizePath($newPath);

        $response = $this->client->post('files/copy', [
            'json' => [
                'from_path' => $path,
                'to_path' => $newPath,
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function delete(string $path): array
    {
        $path = $this->normalizePath($path);

        $response = $this->client->post('files/delete', [
            'json' => [
                'path' => $path,
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * @param string $path
     *
     * @return mixed
     */
    public function createFolder(string $path)
    {
        $path = $this->normalizePath($path);

        $response = $this->client->post('files/create_folder', [
            'json' => [
                'path' => $path,
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        $result['.tag'] = 'folder';

        return $result;
    }

    public function getMetadata(string $path)
    {
        $path = $this->normalizePath($path);

        $response = $this->client->post('files/get_metadata', [
            'json' => [
                'path' => $path,
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function getFile(string $path)
    {
        $dropboxApiArguments = [
            'path' => $this->normalizePath($path),
        ];

        $response = $this->client->post('https://content.dropboxapi.com/2/files/download', [
            'headers' => [
                'Dropbox-API-Arg' => json_encode($dropboxApiArguments),
            ],
        ]);

        return StreamWrapper::getResource($response->getBody());
    }

    public function listContents($path = '', $recursive = false): array
    {
        $path = $this->normalizePath($path);

        $response = $this->client->post('files/list_folder', [
            'json' => [
                'path' => $path,
                'recursive' => $recursive,
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function getTemporaryLink(string $path): string
    {
        $path = $this->normalizePath($path);

        $response = $this->client->post('files/get_temporary_link', [
            'json' => [
                'path' => $path,
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        return $body['link'];
    }

    public function getThumbnail(string $path, string $format = 'jpeg', string $size = 'w64h64'): string
    {
        $dropboxApiArguments = [
            'path' => $this->normalizePath($path),
            'format' => $format,
            'size' => $size
        ];

        $response = $this->client->post('https://content.dropboxapi.com/2/files/get_thumbnail', [
            'headers' => [
                'Dropbox-API-Arg' => json_encode($dropboxApiArguments),
            ],
        ]);

        return (string) $response->getBody();
    }

    public function uploadFromString(string $path, string $mode, string $contents)
    {
        $dropboxApiArguments = [
            'path' => $this->normalizePath($path),
            'mode' => $mode,
            'autorename' => true,
        ];

        $response = $this->client->post('https://content.dropboxapi.com/2/files/upload', [
            'headers' => [
                'Dropbox-API-Arg' => json_encode($dropboxApiArguments),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $contents,
        ]);

        $metadata = json_decode($response->getBody(), true);

        $metadata['.tag'] = 'file';

        return $metadata;
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path,'/');

        if ($path === '') {
            return '';
        }

        return '/'.$path;
    }
}
