<?php

namespace Spatie\FlysystemDropbox\Test;

use GuzzleHttp\Psr7\Response;
use League\Flysystem;
use League\Flysystem\StorageAttributes;
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

    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->prophesize(Client::class);

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal(), 'prefix');
    }

    /** @test */
    public function if_can_write()
    {
        $this->client->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $this->dropboxAdapter->write('something', 'contents', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function if_can_write_to_a_stream()
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
     * @test
     *
     * @dataProvider  metadataProvider
     */
    public function it_can_work_with_meta_date($method)
    {
        $this->client = $this->prophesize(Client::class);
        $this->client->getMetadata('/one')->willReturn([
            '.tag'   => 'file',
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/one',
        ]);

        $this->dropboxAdapter = new DropboxAdapter($this->client->reveal());

        $this->assertInstanceOf(
            StorageAttributes::class,
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

    /** @test */
    public function it_can_read()
    {
        $stream = tmpfile();
        fwrite($stream, 'returndata');
        rewind($stream);

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        $this->assertStringContainsString(
            'returndata',
            $this->dropboxAdapter->read('something')
        );
    }

    /** @test */
    public function it_can_read_a_stream()
    {
        $stream = tmpfile();
        fwrite($stream, 'returndata');
        rewind($stream);

        $this->client->download(Argument::any(), Argument::any())->willReturn($stream);

        $this->assertIsResource($this->dropboxAdapter->readStream('something'));

        fclose($stream);
    }

    /** @test */
    public function it_can_delete()
    {
        $this->client->delete('/prefix/something')->willReturn(['.tag' => 'file']);

        $this->dropboxAdapter->delete('something');
        $this->addToAssertionCount(1);

        $this->dropboxAdapter->deleteDirectory('something');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_can_create_a_directory()
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

    /** @test */
    public function it_can_list_contents_of_a_directory()
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
        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_can_move_a_file()
    {
        $this->client->move(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->dropboxAdapter->move('something', 'something', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_can_handle_a_failing_move()
    {
        $this->client->move('/prefix/something', '/prefix/something')->willThrow(new BadRequest(new Response(409)));

        $this->expectException(Flysystem\UnableToMoveFile::class);
        $this->dropboxAdapter->move('something', 'something', new Flysystem\Config());
    }

    /** @test */
    public function it_can_copy()
    {
        $this->client->copy(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->dropboxAdapter->copy('something', 'something', new Flysystem\Config());
        $this->addToAssertionCount(1);
    }

    public function it_can_handle_a_failing_copy()
    {
        $this->client->copy(Argument::any(), Argument::any())->willThrow(new BadRequest(new Response(409)));

        $this->expectException(Flysystem\UnableToCopyFile::class);
        $this->dropboxAdapter->copy('something', 'something', new Flysystem\Config());
    }

    public function testGetClient()
    {
        $this->assertInstanceOf(
            Client::class,
            $this->dropboxAdapter->getClient()
        );
    }
}
