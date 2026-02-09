<?php

use GuzzleHttp\Psr7\Response;
use League\Flysystem\Config;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToMoveFile;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;
use Spatie\FlysystemDropbox\DropboxAdapter;

beforeEach(function () {
    $this->client = Mockery::mock(Client::class);

    $this->dropboxAdapter = new DropboxAdapter($this->client, 'prefix');
});

it('can write', function () {
    $this->client
        ->shouldReceive('upload')
        ->once()
        ->andReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

    $this->dropboxAdapter->write('something', 'contents', new Config);
});

it('can write to a stream', function () {
    $this->client
        ->shouldReceive('upload')
        ->once()
        ->andReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

    $this->dropboxAdapter->writeStream('something', tmpfile(), new Config);
});

it('can work with metadata', function (string $method) {
    $client = Mockery::mock(Client::class);
    $client
        ->shouldReceive('getMetadata')
        ->with('/one')
        ->andReturn([
            '.tag' => 'file',
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/one',
        ]);

    $adapter = new DropboxAdapter($client);

    expect($adapter->{$method}('one'))->toBeInstanceOf(StorageAttributes::class);
})->with([
    'visibility',
    'mimeType',
    'lastModified',
    'fileSize',
]);

it('can provide checksum', function (?string $algo, string $expected) {
    $client = Mockery::mock(Client::class);
    $client
        ->shouldReceive('getMetadata')
        ->with('/one')
        ->andReturn([
            '.tag' => 'file',
            'path_display' => '/one',
            'content_hash' => 'f09112da3439cff97fd750b73b1566bb62a85c1b8688985b1727b79530352af9',
        ]);

    $adapter = new DropboxAdapter($client);

    expect($adapter->checksum('one', new Config([
        'checksum_algo' => $algo,
    ])))->toBe($expected);
})->with([
    [null, 'f09112da3439cff97fd750b73b1566bb62a85c1b8688985b1727b79530352af9'],
    ['sha256', 'f09112da3439cff97fd750b73b1566bb62a85c1b8688985b1727b79530352af9'],
    ['md5', 'e144e4defa1a6018ef003f96dad28a5b'],
]);

it('can read', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client
        ->shouldReceive('download')
        ->once()
        ->andReturn($stream);

    expect($this->dropboxAdapter->read('something'))->toContain('returndata');
});

it('can read a stream', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client
        ->shouldReceive('download')
        ->once()
        ->andReturn($stream);

    $result = $this->dropboxAdapter->readStream('something');
    expect($result)->toBeResource();

    fclose($result);
});

it('can delete', function () {
    $this->client
        ->shouldReceive('delete')
        ->with('/prefix/something')
        ->twice()
        ->andReturn(['.tag' => 'file']);

    $this->dropboxAdapter->delete('something');
    $this->dropboxAdapter->deleteDirectory('something');
});

it('can create a directory', function () {
    $this->client
        ->shouldReceive('createFolder')
        ->with('/prefix/fail/please')
        ->andThrow(new BadRequest(new Response(409)));

    $this->dropboxAdapter->createDirectory('fail/please', new Config);
})->throws(UnableToCreateDirectory::class);

it('can create a directory successfully', function () {
    $this->client
        ->shouldReceive('createFolder')
        ->with('/prefix/pass/please')
        ->once()
        ->andReturn([
            '.tag' => 'folder',
            'path_display' => '/prefix/pass/please',
        ]);

    $this->dropboxAdapter->createDirectory('pass/please', new Config);

    expect(true)->toBeTrue();
});

it('can list contents of a directory', function () {
    $cursor = 'cursor';

    $this->client
        ->shouldReceive('listFolder')
        ->once()
        ->andReturn([
            'entries' => [
                ['.tag' => 'folder', 'path_display' => '/prefix'],
                ['.tag' => 'file', 'path_display' => '/prefix/file'],
            ],
            'has_more' => true,
            'cursor' => $cursor,
        ]);

    $this->client
        ->shouldReceive('listFolderContinue')
        ->with($cursor)
        ->once()
        ->andReturn([
            'entries' => [
                ['.tag' => 'folder', 'path_display' => '/prefix/dirname2'],
                ['.tag' => 'file', 'path_display' => '/prefix/dirname2/file2'],
            ],
            'has_more' => false,
        ]);

    $result = $this->dropboxAdapter->listContents('', true);
    expect(iterator_to_array($result))->toHaveCount(3);
});

it('can move a file', function () {
    $this->client
        ->shouldReceive('move')
        ->once()
        ->andReturn(['.tag' => 'file', 'path' => 'something']);

    $this->dropboxAdapter->move('something', 'something', new Config);
});

it('can handle a failing move', function () {
    $this->client
        ->shouldReceive('move')
        ->with('/prefix/something', '/prefix/something')
        ->andThrow(new BadRequest(new Response(409)));

    $this->dropboxAdapter->move('something', 'something', new Config);
})->throws(UnableToMoveFile::class);

it('can copy', function () {
    $this->client
        ->shouldReceive('copy')
        ->once()
        ->andReturn(['.tag' => 'file', 'path' => 'something']);

    $this->dropboxAdapter->copy('something', 'something', new Config);
});

it('can handle a failing copy', function () {
    $this->client
        ->shouldReceive('copy')
        ->andThrow(new BadRequest(new Response(409)));

    $this->dropboxAdapter->copy('something', 'something', new Config);
})->throws(UnableToCopyFile::class);

test('getClient', function () {
    expect($this->dropboxAdapter->getClient())->toBeInstanceOf(Client::class);
});
