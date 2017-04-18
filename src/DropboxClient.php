<?php

namespace Spatie\FlysystemDropbox;


use GuzzleHttp\Client;

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

    public function listContents($directory = '', $recursive = false)
    {
        $directory = $directory === '/' ? '' : $directory;

        $response = $this->client->post('files/list_folder', [
            'json' => [
                'path' => $directory,
                'recursive' => $recursive,
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function uploadFromString(string $location, string $mode, string $contents)
    {
        $dropboxApiArguments = [
            'path' => $location,
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

        return json_decode($response->getBody(), true);
    }
}
