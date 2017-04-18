<?php

namespace Spatie\FlysystemDropbox\Test;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Spatie\FlysystemDropbox\DropboxClient;
use League\Flysystem\Config;
use Spatie\FlysystemDropbox\DropboxAdapter as Dropbox;
use Prophecy\Argument;

class DropboxAdapterTest extends TestCase
{
    public function dropboxProvider()
    {
        $mock = $this->prophesize(DropboxClient::class);

        return [
            [new Dropbox($mock->reveal(), 'prefix'), $mock],
        ];
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testWrite($adapter, $mock)
    {
        $mock->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $adapter->write('something', 'contents', new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testUpdate(Dropbox $adapter, $mock)
    {
        $mock->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $adapter->update('something', 'contents', new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testWriteStream(Dropbox $adapter, $mock)
    {
        $mock->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $adapter->writeStream('something', tmpfile(), new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testUpdateStream(Dropbox $adapter, $mock)
    {
        $mock->upload(Argument::any(), Argument::any(), Argument::any())->willReturn([
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/prefix/something',
            '.tag' => 'file',
        ]);

        $result = $adapter->updateStream('something', tmpfile(), new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    public function metadataProvider()
    {
        return [
            ['getMetadata'],
            ['getTimestamp'],
            ['getSize'],
            ['has'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider  metadataProvider
     */
    public function testMetadataCalls($method)
    {
        $mock = $this->prophesize(DropboxClient::class);
        $mock->getMetadata('/one')->willReturn([
            '.tag'   => 'file',
            'server_modified' => '2015-05-12T15:50:38Z',
            'path_display' => '/one'
        ]);

        $adapter = new Dropbox($mock->reveal());
        $this->assertInternalType('array', $adapter->{$method}('one'));
    }

    public function testMetadataFileWasMovedFailure()
    {
        $mock = $this->prophesize(DropboxClient::class);
        $mock->getMetadata('/one')->willThrow(new BadResponseException('ERROR',new Request('POST', '/one')));

        $adapter = new Dropbox($mock->reveal());
        $this->assertFalse($adapter->has('one'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testRead($adapter, $mock)
    {
        $stream = tmpfile();
        fwrite($stream, 'something');
        $mock->getFile(Argument::any(), Argument::any())->willReturn($stream);
        $this->assertInternalType('array', $adapter->read('something'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testReadStream(Dropbox $adapter, $mock)
    {
        $stream = tmpfile();
        fwrite($stream, 'something');
        $mock->getFile(Argument::any(), Argument::any())->willReturn($stream);
        $this->assertInternalType('array', $adapter->readStream('something'));
        fclose($stream);
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testDelete(Dropbox $adapter, $mock)
    {
        $mock->delete('/prefix/something')->willReturn(['.tag' => 'file']);
        $this->assertTrue($adapter->delete('something'));
        $this->assertTrue($adapter->deleteDir('something'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testCreateDir(Dropbox $adapter, $mock)
    {
        $mock->createFolder('/prefix/fail/please')->willReturn(null);
        $mock->createFolder('/prefix/pass/please')->willReturn([
            '.tag' => 'folder',
            'path_display'   => '/prefix/pass/please',
        ]);

        $this->assertFalse($adapter->createDir('fail/please', new Config()));

        $expected = ['path' => 'pass/please', 'type' => 'dir'];
        $this->assertEquals($expected, $adapter->createDir('pass/please', new Config()));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testListContents(Dropbox $adapter, $mock)
    {
        $mock->listContents(Argument::type('string'), Argument::any())->willReturn(
            ['entries' => [
                ['.tag' => 'folder', 'path_display' => 'dirname'],
                ['.tag' => 'file', 'path_display' => 'dirname/file'],
            ]]
        );

        $result = $adapter->listContents('', true);
        $this->assertCount(2, $result);
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testRename($adapter, $mock)
    {
        $mock->move(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);
        $this->assertTrue($adapter->rename('something', 'something'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testRenameFail($adapter, $mock)
    {
        $mock->move('/prefix/something', '/prefix/something')->willThrow(new BadResponseException('ERROR', new Request('POST', '')));

        $this->assertFalse($adapter->rename('something', 'something'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testCopy($adapter, $mock)
    {
        $mock->copy(Argument::type('string'), Argument::type('string'))->willReturn(['.tag' => 'file', 'path' => 'something']);

        $this->assertTrue($adapter->copy('something', 'something'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testCopyFail($adapter, $mock)
    {
        $mock->copy(Argument::any(), Argument::any())->willThrow(new BadResponseException('ERROR', new Request('POST', '')));

        $this->assertFalse($adapter->copy('something', 'something'));
    }

    /**
     * @test
     *
     * @dataProvider  dropboxProvider
     */
    public function testGetClient($adapter)
    {
        $this->assertInstanceOf(DropboxClient::class, $adapter->getClient());
    }
}
