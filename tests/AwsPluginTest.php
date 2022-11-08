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
use Composer\Plugin\PluginInterface;
use Composer\Util\RemoteFilesystem;
use Naderman\Composer\AWS\AwsClient;
use Naderman\Composer\AWS\AwsPlugin;
use Naderman\Composer\AWS\S3RemoteFilesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
/**
 * Composer Plugin tests for AWS functionality
 */
class AwsPluginTest extends TestCase
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface|MockObject
     */
    protected $io;

    protected function setUp(): void
    {
        $this->composer = new Composer();
        $this->composer->setConfig(new Config());

        $this->io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
    }

    /**
     * Non-S3 addresses data provider
     *
     * @return array
     */
    public function getNonS3Addresses()
    {
        return [
            ['http://example.com'],
            ['https://example.com'],
            ['http://example.com/packages.json'],
            ['https://example.com/packages.json']
        ];
    }

    /**
     * S3 addresses data provider
     *
     * @return array
     */
    public function getS3Addresses()
    {
        return [
            ['s3://example.com'],
            ['s3://example'],
            ['s3://example.com/packages.json'],
            ['s3://example/packages.json']
        ];
    }

    /**
     * @dataProvider getNonS3Addresses
     * @param $address
     */
    public function testPluginIgnoresNonS3Protocols($address)
    {
        $plugin = new AwsPlugin();
        $plugin->activate($this->composer, $this->io);
        /** @var PreFileDownloadEvent&MockObject $event */
        $event = $this->getMockBuilder(PreFileDownloadEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn($address);

        if (
            version_compare(PluginInterface::PLUGIN_API_VERSION, '1.0.0', 'ge')
            && version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', 'lt')
        ) {
            $event->expects($this->never())
                ->method('setRemoteFilesystem');
        } else {
            $event->expects($this->never())
                ->method('setProcessedUrl');
        }

        $plugin->onPreFileDownload($event);
    }

    /**
     * @dataProvider getS3Addresses
     * @param $address
     */
    public function testPluginProcessS3Protocols($address)
    {
        $plugin = new AwsPlugin();
        $plugin->activate($this->composer, $this->io);
        /** @var PreFileDownloadEvent&MockObject $event */
        $event = $this->getMockBuilder(PreFileDownloadEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->once())
            ->method('getProcessedUrl')
            ->willReturn($address);

        if (
            version_compare(PluginInterface::PLUGIN_API_VERSION, '1.0.0', 'ge')
            && version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', 'lt')
        ) {
            $remoteFileSystem = $this->getMockBuilder(RemoteFilesystem::class)
                ->disableOriginalConstructor()
                ->getMock();
            $event->expects($this->once())
                ->method('getRemoteFileSystem')
                ->willReturn($remoteFileSystem);

            $remoteFileSystem->expects($this->once())
                ->method('getOptions')
                ->willReturn([]);

            $event->expects($this->once())
                ->method('setRemoteFilesystem')
                ->with($this->isInstanceOf(S3RemoteFilesystem::class));
        } else {
            $event->expects($this->once())
                ->method('setProcessedUrl');
        }

        $plugin->onPreFileDownload($event);
    }
}
