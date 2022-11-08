<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Naderman\Composer\AWS;

use Aws\Exception\CredentialsException;
use Aws\S3\Exception\S3Exception;
use Composer\IO\IOInterface;
use Composer\Config;
use Composer\Downloader\TransportException;

use Aws\S3\S3Client;
use Aws\S3\S3MultiRegionClient;
use Aws\Credentials\CredentialProvider;

/**
 * @author Till Klampaeckel <till@php.net>
 * @author Nils Adermann <naderman@naderman.de>
 */
class AwsClient
{
    /**
     * @var \Composer\Config
     */
    protected $config;

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * @var S3Client[]
     */
    protected $clients = array();

    /**
     * @param \Composer\IO\IOInterface $io
     * @param \Composer\Config         $config
     */
    public function __construct(IOInterface $io, Config $config)
    {
        $this->io       = $io;
        $this->config   = $config;
    }

    /**
     * @param string $url URL of the archive on Amazon S3.
     * @param bool $progress Show progress
     * @param string $to Target file name
     *
     * @return $this
     * @throws \Composer\Downloader\TransportException
     */
    public function download($url, $progress, $to = null)
    {
        list($bucket, $key) = $this->determineBucketAndKey($url);

        if ($progress) {
            $this->io->write("    Downloading: <comment>connection...</comment>", false);
        }

        try {
            $params = array(
                'Bucket'                => $bucket,
                'Key'                   => $key
            );

            if ($to) {
                $params['SaveAs'] = $to;
            }

            $s3     = $this->s3factory($this->config, $bucket);
            $result = $s3->getObject($params);

            if ($progress) {
                $this->io->overwrite("    Downloading: <comment>100%</comment>");
            }

            if ($to) {
                if (false === file_exists($to) || !filesize($to)) {
                    $errorMessage = sprintf(
                        "Unknown error occurred: '%s' was not downloaded from '%s'.",
                        $key,
                        $url
                    );
                    throw new TransportException($errorMessage);
                }
            } else {
                return $result['Body'];
            }
        } catch (CredentialsException $e) {
            $msg = "Please add key/secret or a profile name into config.json or set up an IAM profile for your EC2 instance.";
            throw new TransportException($msg, 403, $e);
        } catch(S3Exception $e) {
            $msg = $e->getAwsErrorMessage();

            throw new TransportException(
                sprintf(
                    "Connection to Amazon S3 failed: %s",
                    isset($msg) ? $msg : $e->getMessage()
                ),
                $e->getStatusCode(),
                $e
            );
        } catch (TransportException $e) {
            throw $e; // just re-throw
        } catch (\Exception $e) {
            throw new TransportException("Problem?", 0, $e);
        }

        return $this;
    }

    /**
     * @param string $url
     *
     * @return array
     */
    public function determineBucketAndKey($url)
    {
        $hostName = parse_url($url, PHP_URL_HOST);
        $path     = substr(parse_url($url, PHP_URL_PATH) ?? "", 1);

        $parts = array();
        if (!empty($path)) {
            $parts = explode('/', $path);
        }

        if ('s3.amazonaws.com' !== $hostName) {
            // replace potential aws hostname
            array_unshift($parts, str_replace('.s3.amazonaws.com', '', $hostName));
        }
        $bucket = array_shift($parts);
        $key = implode('/', $parts);
        if (!$key) {
            $key = '/';
        }
        return array($bucket, $key);
    }

    /**
     * This method reads AWS config and credentials and create s3 client
     * Behaviour aims to mimic region setup as it is for credentials:
     * http://docs.aws.amazon.com/aws-sdk-php/v3/guide/getting-started/basic-usage.html#creating-a-client
     * Which is the following (stopping at the first successful case):
     * 1) read region from config parameter
     * 2) read region from environment variables
     * 3) read region from profile config file
     *
     * @param \Composer\Config $config
     * @param string $bucket
     *
     * @return \Aws\S3\S3Client
     */
    public function s3factory(Config $config, $bucket)
    {
        if (!isset($this->clients[$bucket])) {

            $s3config = array(
                'version' => 'latest'
            );

            if (($composerAws = $config->get(ComposerCredentialProvider::CONFIG_SCOPE))) {
                unset($composerAws[ComposerCredentialProvider::CREDENTIALS_PATH]);

                $s3config = array_merge($s3config, $composerAws);
            }

            /**
             * @todo Is this really necessary? Shouldn't all this stuff be handled
             *       via composer dependencies and autoload?
             */
            /*
            $static_include_path = __DIR__ . '/../../../../../../composer/autoload_static.php';
            if ( file_exists( $static_include_path)){
                // This file has to be loaded with the exact same name as in the composer static autoloader to avoid
                // including it twice, which leads to functions in the AWS Namespace to be declared twice.
                $static_include_path = realpath($static_include_path);
                $static_include_path = dirname($static_include_path);
                require_once   $static_include_path . '/../aws/aws-sdk-php/src/functions.php';
            } else if (!function_exists('AWS\manifest') || !function_exists(('Aws\default_http_handler'))) {
                require_once __DIR__ . '/../../../../../../aws/aws-sdk-php/src/functions.php';
            }

            if (!function_exists('GuzzleHttp\Psr7\Utils::uriFor')) {
                require_once __DIR__ . '/../../../../../../guzzlehttp/psr7/src/functions_include.php';
            }

            if (!function_exists('GuzzleHttp\choose_handler')) {
                require_once __DIR__ . '/../../../../../../guzzlehttp/guzzle/src/functions_include.php';
            }

            if (!function_exists('GuzzleHttp\Promise\queue')) {
                require_once __DIR__ . '/../../../../../../guzzlehttp/promises/src/functions_include.php';
            }
            */

            $credentialProviders = array(
                new ComposerCredentialProvider($config)
            );

            if (isset($s3config['profile'])) {
                $credentialProviders[] = CredentialProvider::ini($s3config['profile']);
            }

            $credentialProviders[] = CredentialProvider::defaultProvider();

            $s3config['credentials'] = call_user_func_array(
                '\Aws\Credentials\CredentialProvider::chain',
                $credentialProviders
            );

            if (!isset($s3config['region'])) {
                $s3config['region'] = (new S3MultiRegionClient($s3config))->determineBucketRegion($bucket);
            }

            $this->clients[$bucket] = new S3Client($s3config);

        }

        return $this->clients[$bucket];
    }

    /**
     * @param string $url
     *
     * @return string Download url
     */
    public function getDownloadUrl($url)
    {
        list($bucket, $key) = $this->determineBucketAndKey($url);

        $s3_client = $this->s3factory($this->config, $bucket);

        $command = $s3_client->getCommand('GetObject', array(
            'Bucket' => $bucket,
            'Key' => $key
        ));

        $request = $s3_client->createPresignedRequest($command, '+2 minutes');

        return (string) $request->getUri();
    }
}
