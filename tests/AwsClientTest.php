<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 * Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Naderman\Composer\AWS;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\RemoteFilesystem;
use Naderman\Composer\AWS\AwsClient;
use Naderman\Composer\AWS\AwsPlugin;
use Naderman\Composer\AWS\S3RemoteFilesystem;

class AwsClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $io;

    public function setUp()
    {
        $this->config = new Config();
        $this->io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
    }
    
    public function getS3Urls()
    {
        return [
            ['s3://my-bucket/', 'my-bucket', '/']
        ];
    }

    /**
     * @dataProvider getS3Urls
     * @param $url
     * @param $bucket
     * @param $key
     */
    public function testBucketAndKeyExtraction($url, $bucket, $key)
    {
        $client = new AwsClient($this->io, $this->config);
        
        $this->assertSame([$bucket, $key], $client->determineBucketAndKey($url));
    }
}
