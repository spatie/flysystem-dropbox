<?php

use GuzzleHttp\Psr7\Response;
use League\Flysystem;
use League\Flysystem\Config;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToMoveFile;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;
use Spatie\FlysystemDropbox\DropboxAdapter;

uses(
    \PHPUnit\Framework\TestCase::class,
    ProphecyTrait::class
);

beforeEach(function () {
    $this->client = $this->prophesize(Client::class);

    $this->dropboxAdapter = new DropboxAdapter($this->client->reveal(), 'prefix');
});

it('can write', function () {
    $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
        'server_modified' => '2015-05-12T15:50:38Z',
        'path_display' => '/prefix/something',
        '.tag' => 'file',
    ]);

    $this->dropboxAdapter->write('something', 'contents', new Config());
    $this->addToAssertionCount(1);
});

it('can write to a stream', function () {
    $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
        'server_modified' => '2015-05-12T15:50:38Z',
        'path_display' => '/prefix/something',
        '.tag' => 'file',
    ]);

    $this->dropboxAdapter->writeStream('something', tmpfile(), new Config());
    $this->addToAssertionCount(1);
});

it('can work with meta date', function (string $method) {
    $this->client = $this->prophesize(Client::class);
    $this->client->getMetadata('/one')->willReturn([
        '.tag' => 'file',
        'server_modified' => '2015-05-12T15:50:38Z',
        'path_display' => '/one',
    ]);

    $this->dropboxAdapter = new DropboxAdapter($this->client->reveal());

    $this->assertInstanceOf(
        StorageAttributes::class,
        $this->dropboxAdapter->{$method}('one')
    );
})->with([
    'visibility',
    'mimeType',
    'lastModified',
    'fileSize',
]);

it('can read', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

    expect(
        $this->dropboxAdapter->read('something')
    )->toContain('returndata');
});

it('can read a stream', function () {
    $stream = tmpfile();
    fwrite($stream, 'returndata');
    rewind($stream);

    $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

    $this->assertIsResource($this->dropboxAdapter->readStream('something'));

    fclose($stream);
});

it('can delete', function () {
    $this->client->delete('/prefix/something')->willReturn(['.tag' => 'file']);

    $this->dropboxAdapter->delete('something');
    $this->addToAssertionCount(1);

    $this->dropboxAdapter->deleteDirectory('something');
    $this->addToAssertionCount(1);
});

it('can create a directory', function () {
    $this->client->createFolder('/prefix/fail/please')->willThrow(new BadRequest(new Response(409)));
    $this->client->createFolder('/prefix/pass/please')->willReturn([
        '.tag' => 'folder',
        'path_display' => '/prefix/pass/please',
    ]);

    $this->dropboxAdapter->createDirectory('fail/please', new Config());

    $this->dropboxAdapter->createDirectory('pass/please', new Config());
    $this->addToAssertionCount(1);
})->throws(UnableToCreateDirectory::class);

it('can list contents to a directory', function () {
    $cursor = 'cursor';

    $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
        [
            'entries' => [
                // This is the prefixed folder itself and shouldn't be shown.
                ['.tag' => 'folder', 'path_display' => '/prefix'],
                ['.tag' => 'file', 'path_display' => '/prefix/file'],
            ],
            'has_more' => true,
            'cursor' => $cursor,
        ]
    );

    $this->client->listFolderContinue(Argument::exact($cursor))->willReturn(
        [
            'entries' => [
                ['.tag' => 'folder', 'path_display' => '/prefix/dirname2'],
                ['.tag' => 'file', 'path_display' => '/prefix/dirname2/file2'],
            ],
            'has_more' => false,
        ]
    );

    $result = $this->dropboxAdapter->listContents('', true);
    expect($result)->toHaveCount(3);
});

it('can move a file', function () {
    $this->client->move(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

    $this->dropboxAdapter->move('something', 'something', new Config());
    $this->addToAssertionCount(1);
});

it('can handle a failing move', function () {
    $this->client->move('/prefix/something', '/prefix/something')->willThrow(new BadRequest(new Response(409)));

    $this->dropboxAdapter->move('something', 'something', new Config());
})->throws(UnableToMoveFile::class);

it('can copy', function () {
    $this->client->copy(
        Argument::type('string'),
        Argument::type('string')
    )->willReturn(['.tag' => 'file', 'path' => 'something']);

    $this->dropboxAdapter->copy('something', 'something', new Config());
    $this->addToAssertionCount(1);
});

it('can handle a failing copy', function () {
    $this->client->copy(Argument::any(), Argument::any())->willThrow(new BadRequest(new Response(409)));

    $this->dropboxAdapter->copy('something', 'something', new Config());
})->throws(Flysystem\UnableToCopyFile::class);

test('getClient')
    ->expect(fn () => $this->dropboxAdapter->getClient())
    ->toBeInstanceOf(Client::class);
