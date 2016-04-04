<?php

namespace Mroca\RequestLogBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseLogger
{
    /** @var string */
    private $mocksDir;

    /** @var bool */
    private $hashQueryParams;

    /** @var bool */
    private $useIndexedAssociativeArray;

    /** @var Filesystem */
    private $filesystem;

    const FILENAME_SEPARATOR = '__';
    const FILENAME_QS_SEPARATOR = '--';

    public function __construct($mocksDir, $hashQueryParams = false, $useIndexedAssociativeArray = false)
    {
        $this->mocksDir = rtrim($mocksDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $this->hashQueryParams = (bool) $hashQueryParams;
        $this->useIndexedAssociativeArray = (bool) $useIndexedAssociativeArray;
        $this->filesystem = new Filesystem();
    }

    /**
     * Empty and recreate the mocks dir.
     */
    public function clearMocksDir()
    {
        $this->filesystem->remove($this->mocksDir);
        $this->filesystem->mkdir($this->mocksDir);
    }

    /**
     * Copy all existing mocks onto a target directory.
     *
     * @param string $targetDir
     */
    public function dumpMocksTo($targetDir)
    {
        if (!$this->filesystem->exists($this->mocksDir)) {
            return;
        }

        $this->filesystem->mirror($this->mocksDir, $targetDir, null, ['override' => true, 'delete' => true]);
    }

    /**
     * Creates a json log file containing the request and the response contents.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return string The new mock file path
     */
    public function logReponse(Request $request, Response $response)
    {
        $filename = $this->getFilePathByRequest($request);
        $requestJsonContent = json_decode($request->getContent(), true);
        $responseJsonContent = json_decode($response->getContent(), true);

        $dumpFileContent = [
            'request' => [
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'parameters' => $request->request->all(),
                'content' => $requestJsonContent ?: $request->getContent(),
            ],
            'response' => [
                'statusCode' => $response->getStatusCode(),
                'contentType' => $response->headers->get('Content-Type'),
                'content' => $responseJsonContent ?: $response->getContent(),
            ],
        ];

        $this->filesystem->dumpFile($this->mocksDir.$filename, self::jsonEncode($dumpFileContent, true));

        return $this->mocksDir.$filename;
    }

    /**
     * Creates a filename string from a request object, with the following schema :
     * `uri/segments?query=string&others#METHOD-md5Content-md5JsonParams.json`.
     *
     * Examples :
     *  GET http://domain.name/categories => /categories/GET__.json
     *  GET http://domain.name/categories/1 => /categories/GET__1.json
     *  GET http://domain.name/categories/1/articles => /categories/1/GET__articles.json
     *  GET http://domain.name/categories/1/articles?order[title]=desc => /categories/1/GET__articles--order[title]=desc.json
     *  POST http://domain.name/categories with content => /categories/POST____a142b.json
     *
     * @param Request $request
     *
     * @return string
     */
    public function getFilePathByRequest(Request $request)
    {
        $requestPathInfo = trim($request->getPathInfo(), '/');
        $requestMethod = $request->getMethod();
        $requestContent = $request->getContent();
        $requestQueryParameters = $request->query->all();
        $requestParameters = $request->request->all();

        $filename = $requestPathInfo;

        // Store base endpoint calls with its children
        if ('' !== $filename && 0 === substr_count($filename, '/')) {
            $filename .= DIRECTORY_SEPARATOR;
        }

        // Add query parameters
        if (count($requestQueryParameters)) {
            $requestQueryParametersString = self::httpBuildQuery(self::sortArray($requestQueryParameters));

            // Url encode filename if needed
            if ($this->hashQueryParams) {
                $requestQueryParametersString = $this->generateFilenameHash($requestQueryParametersString);
            }

            $filename .= self::FILENAME_QS_SEPARATOR.$requestQueryParametersString;
        }

        // Add request content hash
        if ($requestContent) {

            // If JSON, sort data
            $jsonContent = json_decode($requestContent, true);
            if (null !== $jsonContent) {
                $filename .= self::FILENAME_SEPARATOR.$this->generateFilenameHash(self::jsonEncode(self::sortArray($jsonContent)));
            } else {
                $filename .= self::FILENAME_SEPARATOR.$this->generateFilenameHash($requestContent);
            }
        }

        // Add request parameters hash
        if ($requestParameters) {
            $filename .= self::FILENAME_SEPARATOR.$this->generateFilenameHash(self::jsonEncode(self::sortArray($requestParameters)));
        }

        // Add HTTP method
        $filenameArray = explode('/', $filename);
        $filenameArray[count($filenameArray) - 1] = $requestMethod.self::FILENAME_SEPARATOR.end($filenameArray);
        $filename = implode($filenameArray, '/');

        // Add extension
        $filename .= '.json';

        return $filename;
    }

    /**
     * Json encodes and returns a string.
     *
     * @param string $data
     * @param bool   $pretty
     *
     * @return string
     */
    private function jsonEncode($data, $pretty = false)
    {
        $options = JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES;

        if ($pretty) {
            $options += JSON_PRETTY_PRINT;
        }

        return json_encode($data, $options);
    }

    /**
     * Transforms an array onto query string.
     * We are not using http_build_query as this function transforms `foo=[bar]` onto `foo[0]=bar`, and we want `foo[]=bar`.
     *
     * @param array  $data
     * @param string $keyPrefix
     * @param bool   $isChildren
     *
     * @return string
     */
    private function httpBuildQuery($data, $keyPrefix = '', $isChildren = false)
    {
        if (!is_array($data)) {
            return '';
        }

        $result = [];
        $isNonAssociativeArray = self::isNonAssociativeArray($data);

        foreach ($data as $key => $value) {
            if ($isChildren) {
                $key = $isNonAssociativeArray && !$this->useIndexedAssociativeArray ? $keyPrefix.'[]' : $keyPrefix."[$key]";
            } elseif (is_int($key)) {
                $key = $keyPrefix.$key;
            }

            if (is_array($value) || is_object($value)) {
                $result[] = $this->httpBuildQuery($value, $key, true);
                continue;
            }

            $result[] = urlencode($key).'='.urlencode($value);
        }

        return implode('&', $result);
    }

    /**
     * Returns a hash from a string.
     *
     * @param string $data
     *
     * @return string
     */
    private function generateFilenameHash($data)
    {
        return substr(sha1($data), 0, 5);
    }

    /**
     * Sorts an associative array by key and a flat array by values.
     *
     * @param array $data
     *
     * @return array
     */
    private static function sortArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        if (self::isNonAssociativeArray($data)) {
            sort($data);
        } else {
            ksort($data);
        }

        foreach ($data as $k => $v) {
            $data[$k] = self::sortArray($data[$k]);
        }

        return $data;
    }

    /**
     * Returns true if the array is detected as non-associative.
     *
     * @param array $data
     *
     * @return bool
     */
    private static function isNonAssociativeArray($data)
    {
        return is_array($data) && array_keys($data) === range(0, count($data) - 1);
    }
}
