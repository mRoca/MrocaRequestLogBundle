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
    protected $filesystem;

    /** @var string */
    protected $workspace;

    /** @var ResponseLogger */
    protected $responseLogger;

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

        self::assertNotTrue(is_file($this->workspace.DIRECTORY_SEPARATOR.'file'));
        self::assertNotTrue(is_dir($this->workspace.DIRECTORY_SEPARATOR.'dir'));
        self::assertTrue(is_dir($this->workspace));
    }

    public function testDumpMocksTo()
    {
        $targetPath = $this->createTempDir();
        $this->filesystem->touch($targetPath.DIRECTORY_SEPARATOR.'badfile');

        $this->responseLogger->dumpMocksTo($targetPath);

        self::assertTrue(is_file($targetPath.DIRECTORY_SEPARATOR.'file'));
        self::assertTrue(is_dir($targetPath.DIRECTORY_SEPARATOR.'dir'));
        self::assertNotTrue(is_file($targetPath.DIRECTORY_SEPARATOR.'badfile'));

        $this->filesystem->remove($targetPath);
    }

    public function testLogGetReponse()
    {
        $request = Request::create('/categories?order[name]=asc&limit=5', 'GET');
        $response = Response::create(json_encode(['foo' => 'bar']), Response::HTTP_OK, ['Content-Type' => 'application/json']);

        $file = $this->responseLogger->logReponse($request, $response);

        self::assertTrue(is_file($file));

        self::assertJsonStringEqualsJsonFile($file, '{
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

        self::assertTrue(is_file($file));

        self::assertJsonStringEqualsJsonFile($file, '{
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

        self::assertSame('categories/POST____22845.json', $filename);
    }

    public function testGetEncodedFilePathByRequest()
    {
        $responseLogger = new ResponseLogger($this->workspace, true);
        $request = Request::create('/categories?order[foo]=asc&order[bar]=desc', 'GET');

        $filename = $responseLogger->getFilePathByRequest($request);

        self::assertSame('categories/GET__--90150.json', $filename);
    }

    /**
     * @dataProvider requestsMocksNamesProvider
     *
     * @param Request $request
     * @param         $expectedFilename
     */
    public function testMockFilenames(Request $request, $expectedFilename)
    {
        $filename = $this->responseLogger->getFilePathByRequest($request);

        self::assertSame($expectedFilename, $filename, sprintf('Invalid filename for request %s %s', $request->getMethod(), $request->getRequestUri()));
    }

    public function requestsMocksNamesProvider()
    {
        return [
            [Request::create('/', 'GET'), 'GET__.json'],
            [Request::create('/categories', 'GET'), 'categories/GET__.json'],
            [Request::create('/categories?order[foo]=asc&order[bar]=desc', 'GET'), 'categories/GET__--order%5Bbar%5D=desc&order%5Bfoo%5D=asc.json'],
            [Request::create('/categories?parent=/my/iri&name=foo+bar', 'GET'), 'categories/GET__--name=foo%20bar&parent=%2Fmy%2Firi.json'],
            [Request::create('/categories/1', 'GET'), 'categories/GET__1.json'],
            [Request::create('/categories/1/articles', 'GET'), 'categories/1/GET__articles.json'],
            [Request::create('/categories', 'POST', ['foo1' => 'bar1', 'foo2' => 'bar2']), 'categories/POST____3e038.json'],
            [Request::create('/categories', 'POST', ['foo1' => 'b/ar', 'foo2' => 'b&nbsp;ar']), 'categories/POST____293e3.json'],
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
