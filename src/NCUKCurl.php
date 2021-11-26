<?php

namespace NCUKPhx\PhpCurl;

use NCUKPhx\PhpCurl\NUCKCurlInterface;

/**
 * NCUK Phx php curl will helps request the provided URL and get the response
 */
class NCUKCurl implements NCUKCurlInterface
{

    /**
     * @var curl_init() 
     */
    private $curl;

    /**
     * @var string
     */
    private $error = '';

    /**
     * @var array
     */
    private $ncukResponse = [];
    
    /**
     * Deafult CURLOPT_TIMEOUT value
     */
    const DEFAULT_CURLOPT_TIMEOUT = 59;

    /**
     * returns response from the request url
     * 
     * @param string $method
     * @param string $url
     * @param array $additional, accept data, headerData, allowedRequestHeaderKeys
     * @return array
     * @throws \Exception
     */
    public function call(string $method, string $url, array $additional = []): array
    {
        $this->curl = curl_init();

        // @CRC - need to change into array instead of json - string
        $data = $additional['data'] ?? '';
        if (is_array($data)) {
            $data = json_encode($data);
        }

        /**
         * @internal merging with request headers and passed to request endpoint
         * Expected format: [0=> 'headerKey' => HeaderValue', .... ]
         */
        $headerData = $additional['headerData'] ?? [];
        /**
         * @internal allowed request headers key
         * Expected format: ['headerKey1', 'HeaderKey2', ....]
         */
        $allowedRequestHeaderKeys = $additional['allowedRequestHeaderKeys'] ?? [];
        // default empty string, if we need we can using gzip, etc
        $encoding = $additional['encoding'] ?? '';
        // default false, if we need response headers can be passed as true
        $enableResponseHeader = (isset($additional['enableResponseHeader']) && ($additional['enableResponseHeader'] == true)) ? true : false;
        // default empty string, if use filePath in PUT Method we can use
        $filePath = $additional['filePath'] ?? '';
        /**
         * @internal curl options, contains an array of option and it's value
         * Expected format: [CURLOPT_TIMEOUT => 30, .....]
         * CURLOPT_TIMEOUT is a constant by php-curl
         */
        $curlOptions = $additional['curlOptions'] ?? [];

        try {
            switch ($method) {
                case 'POST':
                    curl_setopt($this->curl, CURLOPT_POST, 1);
                    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
                    break;
                case 'PUT':
                    if (!empty($filePath)) {
                        curl_setopt($this->curl, CURLOPT_PUT, 1);
                        $fh_res = fopen($filePath, 'r');
                        curl_setopt($this->curl, CURLOPT_INFILE, $fh_res);
                        $headerData[] = 'Accept: application/json';
                    } else {
                        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
                    }
                    break;
                case 'DELETE':
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    break;
                case 'PATCH':
                    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    if (!empty($data)) {
                        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
                    }
                    break;
                case 'GET':
                default:
                    curl_setopt($this->curl, CURLOPT_HTTPGET, 1);
                    break;
            }

            $headerArray = $this->_getRequestHeader($allowedRequestHeaderKeys);
            if (!empty($headerData)) {
                // while merging request header and header passed through parameter, first preference will given to requestHeader
                $headerArray = array_merge($headerData, $headerArray);
            }

            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headerArray); // set cURL Headers
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->curl, CURLOPT_ENCODING, $encoding);
            curl_setopt($this->curl, CURLOPT_URL, $url);
            curl_setopt($this->curl, CURLOPT_HEADER, $enableResponseHeader);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, NCUKCurl::DEFAULT_CURLOPT_TIMEOUT);
            // header('Access-Control-Allow-Origin: *');
             
            // setting curl option only if we wanted to pass
            if (!empty($curlOptions) && is_array($curlOptions)) {
                foreach ($curlOptions as $option => $value) {
                    curl_setopt($this->curl, $option, $value);
                }
            }

            $response = curl_exec($this->curl);
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

            $this->ncukResponse['code'] = $httpCode ?? 500;

            // checking if any error
            $error = curl_error($this->curl);
            if (!empty($error)) {
                $this->ncukResponse['data'] = '';
                //Returning curl error number in case of any error
                $this->ncukResponse['curlErrNo'] = curl_errno($this->curl);
                $this->ncukResponse['error'] = $error;
                throw new \Exception($error);
            }

            $this->ncukResponse['data'] = $response;
            if ($enableResponseHeader == true) {
                $this->_parseResponse($response, $this->ncukResponse);
            }

            curl_close($this->curl);
            return $this->ncukResponse;
        } catch (\Exception $ex) {
            curl_close($this->curl);
            //http code for exception
            $this->_setError($ex->getMessage());
            throw new \Exception('NCUKPhx Php Curl Exception: ' . $ex->getMessage() . ' at line - ' . $ex->getLine() . ' in file ' . $ex->getFile());
        }
    }

    /**
     * Returns error
     * 
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * Returns response of the curl
     * 
     * @return array
     */
    public function getResponse(): array
    {
        if (!empty($this->getError())) {
            $this->ncukResponse['error'] = $this->getError();
        }

        return $this->ncukResponse;
    }

    /**
     * Returns all request headers
     * 
     * @param  array $allowedHeaderKeys
     * @return array
     */
    private function _getRequestHeader(array $allowedHeaderKeys = []): array
    {
        $requestHeaders = $this->_getAllHeaders();
        if (empty($allowedHeaderKeys)) {
            return $this->_sanitizeHeaderParameter($requestHeaders);
        }

        $allowedHeaders = [];
        // filter based $allowedHeaderKeys
        foreach ($allowedHeaderKeys as $headerKey) {
            if (!empty($requestHeaders[$headerKey])) {
                $allowedHeaders[] = $headerKey . ':' . $requestHeaders[$headerKey];
            }
        }
        return $allowedHeaders ?? $this->_sanitizeHeaderParameter($requestHeaders);
    }

    /**
     * Returns sanitized header from headers with key value pair
     * 
     * @param array $headers
     * @return array
     */
    private function _sanitizeHeaderParameter(array $headers): array
    {
        $sanitizedHeaders = [];
        try {
            if (isset($headers['Content-Length'])) {
                unset($headers['Content-Length']);
            }

            foreach ($headers as $headerKey => $headerValue) {
                $sanitizedHeaders[] = $headerKey . ':' . $headerValue;
            }
            return $sanitizedHeaders;
        } catch (\Exception $ex) {
            return $sanitizedHeaders;
        }
    }

    /**
     * Set error if any issue occur while call package
     * 
     * @param string $error
     * @return void
     */
    private function _setError(string $error): void
    {
        $this->error = $error;
    }

    /**
     * Method for parsing body & header data
     * 
     * @param string $response
     * @param array $parsedResponse
     * @return void
     */
    private function _parseResponse(string $response, array &$parsedResponse): void
    {
        try {
            $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $headerSize);
            $headerText = substr($header, 0, strpos($header, "\r\n\r\n"));

            $data = substr($response, $headerSize);
            $parsedResponse['data'] = $data;

            $headerTextArray = explode("\r\n", $headerText);
            if (empty($headerTextArray)) {
                return;
            }

            foreach ($headerTextArray as $i => $line) {
                if ($i === 0) {
                    $headers['http_code'] = $line;
                } else {
                    list ($key, $value) = explode(': ', $line);
                    $headers[$key] = $value;
                }
            }
            $parsedResponse['header'] = $headers;
        } catch (\Exception $ex) {
            return;
        }
    }
    
    /**
     * Return all header from the request
     * @return array
     */
    private function _getAllHeaders(): array
    {
        /**
         * @internal in case using php-fpm we may get undefined error for getallheaders
         */
        if (function_exists("getallheaders")) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

}
