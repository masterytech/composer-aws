<?php
namespace Naderman\Composer\AWS;

use Aws\Credentials\Credentials;
use Aws\Exception\CredentialsException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Credential provider that provides credentials from the EC2 metadata server.
 */
class ComposerCredentialProvider
{
    const CONFIG_SCOPE = 'amazon-aws';
    const CREDENTIALS_PATH = 'credentials';
    const CREDENTIALS_PATH_KEY = 'key';
    const CREDENTIALS_PATH_SECRET = 'secret';
    const NO_CONFIG_ERROR_MESSAGE = 'No or incomplete credentials provided for Composer.';

    /**
     * @var \Composer\Config
     */
    protected $config;

    /**
     * The constructor accepts the following options:
     *
     * - config: the current composer config
     *
     * @param \Composer\Config $config Composer configuration.
     */
    public function __construct(\Composer\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Loads composer credentials.
     *
     * @return PromiseInterface
     */
    public function __invoke()
    {
        $config = $this->config->get(self::CONFIG_SCOPE);
        if (!isset($config, $config[self::CREDENTIALS_PATH])) {
            return new Promise\RejectedPromise(new CredentialsException(self::NO_CONFIG_ERROR_MESSAGE));
        }

        $credentials = isset($config[self::CREDENTIALS_PATH]);
        if (!isset(
            $credentials[self::CREDENTIALS_PATH_KEY],
            $credentials[self::CREDENTIALS_PATH_SECRET]
        )) {
            return new Promise\RejectedPromise(new CredentialsException(self::NO_CONFIG_ERROR_MESSAGE));
        }

        return Promise\promise_for(
            new Credentials(
                $credentials[self::CREDENTIALS_PATH_KEY],
                $credentials[self::CREDENTIALS_PATH_SECRET]
            )
        );
    }
}
