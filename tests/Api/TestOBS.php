<?php

namespace Tests\Api;

use Fize\Third\PingAn\Api\OBS;
use PHPUnit\Framework\TestCase;

class TestOBS extends TestCase
{
    public function testPutObject()
    {
        set_time_limit(0);
        $file = '/temp/test.pdf';
        $access_key = 'NEM0QTcxN0JEMjE3NEJBNzk1OUMwQ0YxQTA2NUEyRUU';
        $secret_key = 'RDVBMkEzMjJFRjBDNDVDMzk4ODdEQTU5QTlBNzYyNDM';
        $file_key = 'test/test2.pdf';
        $obs = new OBS($access_key, $secret_key);
        $obs->setBucket('qcjr');
        $result = $obs->putObject($file, $file_key);
        var_dump($result);
        $url = $obs->getSignedUrl($file_key);
        var_dump($url);
    }

    public function testGetSignedUrl()
    {
        $config = [
            'accessKey' => 'NEM0QTcxN0JEMjE3NEJBNzk1OUMwQ0YxQTA2NUEyRUU',
            'secretKey' => 'RDVBMkEzMjJFRjBDNDVDMzk4ODdEQTU5QTlBNzYyNDM',
            'bucket'    => 'qcjr'
        ];
        $obs = new OBS($config['accessKey'], $config['secretKey']);
        $obs->setBucket($config['bucket']);
        $url = $obs->getSignedUrl('image%2Fscreenshots%2F222403007.png');
        echo $url;
    }

    public function testPutObjectMultipart()
    {
        $access_key = 'NEM0QTcxN0JEMjE3NEJBNzk1OUMwQ0YxQTA2NUEyRUU';
        $secret_key = 'RDVBMkEzMjJFRjBDNDVDMzk4ODdEQTU5QTlBNzYyNDM';
        $obs = new OBS($access_key, $secret_key);
        $file = '/temp/test.pdf';
        $part_size = 2 * 1024 * 1024;
        $result = $obs->putObjectMultipart($file, $part_size);
        self::assertIsBool($result);
    }
}
