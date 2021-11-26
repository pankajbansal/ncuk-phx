<?php

namespace NCUKPhx\PhpCurl\Tests;

use PHPUnit\Framework\TestCase;
use NCUKPhx\PhpCurl\NCUKCurl;

/**
 * This class will handle all the curl calls
 */
final class NCUKCurlTest extends TestCase
{
    /**
     * Unit test for call method
     * @return void
     * @covers NCUKCurl::call
     * @dataProvider dataProviderCall
     * @testWith
     * @uses
     */
    public function testCall(array $requestInput, array $expectedResponse): void
    {
        $curl = new NCUKCurl();
        $response = $curl->call($requestInput['method'], $requestInput['url']);
        $this->assertEquals(
                $expectedResponse['code'],
                $response['code']
        );
    }

    /**
     * 
     * @return array
     */
    public function dataProviderCall(): array
    {        
        return [
            [
                ['method' => 'GET', 'url' => 'www.example.com'],
                ['code' => 200]
            ]
        ];
    }

}
