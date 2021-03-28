<?php

namespace Spatie\FlysystemDropbox\Test;

use GuzzleHttp\Psr7\Response;
use League\Flysystem;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxAdapterTest extends TestCase
{
    use ProphecyTrait;

    /** @var \Spatie\Dropbox\Client|\Prophecy\Prophecy\ObjectProphecy */
    protected $client;

    /** @var \Spatie\FlysystemDropbox\DropboxAdapter */
    protected $dropboxAdapter;

    /**
     * @before
     */
    public function setUpTest(): void
    {
        $this->client = $this->prophesize(Client::class);

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal(), 'prefix');
    }

    public function testWrite(): void
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $this->dropboxAdapter->write('something', 'contents', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    public function testWriteStream(): void
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $this->dropboxAdapter->writeStream('something', tmpfile(), new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider  metadataProvider
     */
    public function testMetadataCalls($method): void
    {
        $this->client = $this->prophesize(Client::class);
        $this->client->getMetadata('/one')->willReturn([
            '.tag'   => 'file',
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/one',
        ]);

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal());

        self::assertInstanceOf(
            Flysystem\StorageAttributes::class,
            $this->dropboxAdapter->{$method}('one')
        );
    }

    public function metadataProvider(): array
    {
        return [
            ['visibility'],
            ['mimeType'],
            ['lastModified'],
            ['fileSize'],
        ];
    }

    public function testRead(): void
    {
        $stream = tmpfile();
        fwrite($stream, 'returndata');
        rewind($stream);

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        self::assertStringContainsString(
            'returndata',
            $this->dropboxAdapter->read('something')
        );
    }

    public function testReadStream(): void
    {
        $stream = tmpfile();
        fwrite($stream, 'returndata');
        rewind($stream);

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        self::assertIsResource($this->dropboxAdapter->readStream('something'));

        fclose($stream);
    }

    public function testDelete(): void
    {
        $this->client->delete('/prefix/something')->willReturn(['.tag' => 'file']);

        $this->dropboxAdapter->delete('something');
        $this->addToAssertionCount(1);

        $this->dropboxAdapter->deleteDirectory('something');
        $this->addToAssertionCount(1);
    }

    public function testCreateDirectory(): void
    {
        $this->client->createFolder('/prefix/fail/please')->willThrow(new BadRequest(new Response(409)));
        $this->client->createFolder('/prefix/pass/please')->willReturn([
            '.tag' => 'folder',
            'path_display'   => '/prefix/pass/please',
        ]);

        $this->expectException(Flysystem\UnableToCreateDirectory::class);
        $this->dropboxAdapter->createDirectory('fail/please', new Flysystem\Config());

        $this->dropboxAdapter->createDirectory('pass/please', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    public function testListContents(): void
    {
        $cursor = 'cursor';

        $this->client->listFolder(Argument::type('string'), Argument::any())->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname'],
                    ['.tag' => 'file', 'path_display' => 'dirname/file'],
                ],
                'has_more' => true,
                'cursor' => $cursor,
            ]
        );

        $this->client->listFolderContinue(Argument::exact($cursor))->willReturn(
            [
                'entries' => [
                    ['.tag' => 'folder', 'path_display' => 'dirname2'],
                    ['.tag' => 'file', 'path_display' => 'dirname2/file2'],
                ],
                'has_more' => false,
            ]
        );

        $result = $this->dropboxAdapter->listContents('', true);
        self::assertCount(4, $result);
    }

    public function testMove(): void
    {
        $this->client->move(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->dropboxAdapter->move('something', 'something', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    public function testMoveFail(): void
    {
        $this->client->move('/prefix/something', '/prefix/something')->willThrow(new BadRequest(new Response(409)));

        $this->expectException(Flysystem\UnableToMoveFile::class);
        $this->dropboxAdapter->move('something', 'something', new Flysystem\Config());
    }

    public function testCopy(): void
    {
        $this->client->copy(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->dropboxAdapter->copy('something', 'something', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    public function testCopyFail(): void
    {
        $this->client->copy(Argument::any(), Argument::any())->willThrow(new BadRequest(new Response(409)));

        $this->expectException(Flysystem\UnableToCopyFile::class);
        $this->dropboxAdapter->copy('something', 'something', new Flysystem\Config());
    }

    public function testGetClient(): void
    {
        self::assertInstanceOf(
            Client::class,
            $this->dropboxAdapter->getClient()
        );
    }
}
