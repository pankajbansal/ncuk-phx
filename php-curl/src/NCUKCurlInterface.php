<?php

namespace NCUKPhx\PhpCurl;

/**
 * NCUK Phx php curl will helps request the provided URL and get the response
 */
interface NCUKCurlInterface
{
    /**
     * returns response from the request url
     * 
     * @param string $method
     * @param string $url
     * @param array $additional
     * @return array
     */
    public function call(string $method, string $url, array $additional = []): array;
    
    /**
     * Returns response of the curl
     * @return array
     */
    public function getResponse(): array;

    /**
     * Returns error
     * 
     * @return string
     */
    public function getError(): string;

}
