<?php

namespace Mroca\RequestLogBundle\Tests\Service;

use Mroca\RequestLogBundle\Service\ResponseLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseLoggerTest extends \PHPUnit_Framework_TestCase
{
    /** @var int */
    private $umask;

    /** @var \Symfony\Component\Filesystem\Filesystem */
    protected $filesystem = null;

    /** @var string */
    protected $workspace = null;

    /** @var ResponseLogger */
    protected $responseLogger = null;

    protected function setUp()
    {
        $this->umask = umask(0);
        $this->filesystem = new Filesystem();
        $this->workspace = $this->createTempDir();
        $this->responseLogger = new ResponseLogger($this->workspace);

        $this->filesystem->mkdir($this->workspace.DIRECTORY_SEPARATOR.'dir');
        $this->filesystem->touch($this->workspace.DIRECTORY_SEPARATOR.'file');
    }

    protected function tearDown()
    {
        $this->filesystem->remove($this->workspace);
        umask($this->umask);
    }

    public function testClearMocksDir()
    {
        $this->responseLogger->clearMocksDir();

        $this->assertTrue(!is_file($this->workspace.DIRECTORY_SEPARATOR.'file'));
        $this->assertTrue(!is_dir($this->workspace.DIRECTORY_SEPARATOR.'dir'));
        $this->assertTrue(is_dir($this->workspace));
    }

    public function testDumpMocksTo()
    {
        $targetPath = $this->createTempDir();
        $this->filesystem->touch($targetPath.DIRECTORY_SEPARATOR.'badfile');

        $this->responseLogger->dumpMocksTo($targetPath);

        $this->assertTrue(is_file($targetPath.DIRECTORY_SEPARATOR.'file'));
        $this->assertTrue(is_dir($targetPath.DIRECTORY_SEPARATOR.'dir'));
        $this->assertTrue(!is_file($targetPath.DIRECTORY_SEPARATOR.'badfile'));

        $this->filesystem->remove($targetPath);
    }

    public function testLogGetReponse()
    {
        $request = Request::create('/categories?order[name]=asc&limit=5', 'GET');
        $response = Response::create(json_encode(['foo' => 'bar']), Response::HTTP_OK, ['Content-Type' => 'application/json']);

        $file = $this->responseLogger->logReponse($request, $response);

        $this->assertTrue(is_file($file));

        $this->assertJsonStringEqualsJsonFile($file, '{
            "request": {
                "uri": "/categories?order[name]=asc&limit=5",
                "method": "GET",
                "parameters": [],
                "content": ""
            },
            "response": {
                "statusCode": 200,
                "contentType": "application/json",
                "content": {
                    "foo": "bar"
                }
            }
        }');
    }

    public function testLogPostReponse()
    {
        $request = Request::create('/categories', 'POST', ['key' => 'value']);
        $response = Response::create('', Response::HTTP_CREATED);

        $file = $this->responseLogger->logReponse($request, $response);

        $this->assertTrue(is_file($file));

        $this->assertJsonStringEqualsJsonFile($file, '{
            "request": {
                "uri": "/categories",
                "method": "POST",
                "parameters": {
                    "key": "value"
                },
                "content": ""
            },
            "response": {
                "statusCode": 201,
                "contentType": null,
                "content": ""
            }
        }');
    }

    public function testGetFilePathByRequest()
    {
        $request = Request::create('/categories', 'POST', ['key' => 'value']);

        $filename = $this->responseLogger->getFilePathByRequest($request);

        $this->assertSame('categories/POST____22845.json', $filename);
    }

    public function testGetEncodedFilePathByRequest()
    {
        $responseLogger = new ResponseLogger($this->workspace, true);
        $request = Request::create('/categories?order[foo]=asc&order[bar]=desc', 'GET');

        $filename = $responseLogger->getFilePathByRequest($request);

        $this->assertSame('categories/GET__--b0324.json', $filename);
    }

    /**
     * @dataProvider requestsMocksNamesProvider
     */
    public function testMockFilenames(Request $request, $expectedFilename)
    {
        $filename = $this->responseLogger->getFilePathByRequest($request);

        $this->assertSame($expectedFilename, $filename, sprintf('Invalid filename for request %s %s', $request->getMethod(), $request->getRequestUri()));
    }

    public function requestsMocksNamesProvider()
    {
        return [
            [Request::create('/', 'GET'), 'GET__.json'],
            [Request::create('/categories', 'GET'), 'categories/GET__.json'],
            [Request::create('/categories?order[foo]=asc&order[bar]=desc', 'GET'), 'categories/GET__--order[bar]=desc&order[foo]=asc.json'],
            [Request::create('/categories/1', 'GET'), 'categories/GET__1.json'],
            [Request::create('/categories/1/articles', 'GET'), 'categories/1/GET__articles.json'],
            [Request::create('/categories', 'POST', ['foo1' => 'bar1', 'foo2' => 'bar2']), 'categories/POST____3e038.json'],
            [Request::create('/categories', 'POST', ['foo2' => 'bar2', 'foo1' => 'bar1']), 'categories/POST____3e038.json'],
            [Request::create('/categories', 'POST', [], [], [], [], 'foobar'), 'categories/POST____8843d.json'],
            [Request::create('/categories', 'POST', [], [], [], [], json_encode(['foo' => 'bar'])), 'categories/POST____a5e74.json'],
            [Request::create('/categories', 'POST', ['foo2' => 'bar2', 'foo1' => 'bar1'], [], [], [], json_encode(['foo' => 'bar'])), 'categories/POST____a5e74__3e038.json'],
            [Request::create('/categories/1', 'PUT', [], [], [], [], json_encode(['foo' => 'bar'])), 'categories/PUT__1__a5e74.json'],
        ];
    }

    protected function createTempDir()
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.time().mt_rand(0, 1000);
        $this->filesystem->mkdir($dir, 0777);

        return realpath($dir);
    }
}
