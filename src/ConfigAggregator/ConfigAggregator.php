<?php
/**
 * @see       https://github.com/zendframework/zend-config-aggregator for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @copyright Copyright (c) 2015-2016 Mateusz Tymek (http://mateusztymek.pl)
 * @license   https://github.com/zendframework/zend-config-aggregator/blob/master/LICENSE.md New BSD License
 */
namespace ZendServer\DepH\ConfigAggregator;

use Closure;
use Generator;
use Zend\Stdlib\ArrayUtils\MergeRemoveKey;
use Zend\Stdlib\ArrayUtils\MergeReplaceKeyInterface;
use ZendServer\DepH\ConfigAggregator\Exception\InvalidConfigProviderException;

/**
 * Aggregate configuration generated by configuration providers.
 */
class ConfigAggregator
{
    const ENABLE_CACHE = 'config_cache_enabled';
    const CACHE_TEMPLATE = <<< 'EOT'
<?php
/**
 * This configuration cache file was generated by %s
 * at %s
 */
return %s;
EOT;
    /**
     * @var array
     */
    private $config;
    /**
     * @param array $providers Array of providers. These may be callables, or
     *     string values representing classes that act as providers. If the
     *     latter, they must be instantiable without constructor arguments.
     * @param null|string $cachedConfigFile Configuration cache file; config is
     *     loaded from this file if present, and written to it if not. null
     *     disables caching.
     */
    public function __construct(
        array $providers = array(),
        $cachedConfigFile = null
    ) {
        if ($this->loadConfigFromCache($cachedConfigFile)) {
            return;
        }
        $this->config = $this->loadConfigFromProviders($providers);
        $this->cacheConfig($this->config, $cachedConfigFile);
    }
    /**
     * @return array
     */
    public function getMergedConfig()
    {
        return $this->config;
    }
    /**
     * Resolve a provider.
     *
     * If the provider is a string class name, instantiates that class and
     * tests if it is callable, returning it if true.
     *
     * If the provider is a callable, returns it verbatim.
     *
     * Raises an exception for any other condition.
     *
     * @param string|callable $provider
     * @return callable
     * @throws InvalidConfigProviderException
     */
    private function resolveProvider($provider)
    {
        if (is_string($provider)) {
            if (! class_exists($provider)) {
                throw new InvalidConfigProviderException("Cannot read config from $provider - class cannot be loaded.");
            }
            $provider = new $provider();
        }
        if (! is_callable($provider)) {
            throw new InvalidConfigProviderException(
                sprintf("Cannot read config from %s - config provider must be callable.", get_class($provider))
            );
        }
        return $provider;
    }
    /**
     * Perform a recursive merge of two multi-dimensional arrays.
     *
     * Copied from https://github.com/zendframework/zend-stdlib/blob/980ce463c29c1a66c33e0eb67961bba895d0e19e/src/ArrayUtils.php#L269
     *
     * @param array $a
     * @param array $b
     * @return $a
     */
    private function mergeArray(array $a, array $b)
    {
        foreach ($b as $key => $value) {
            if ($value instanceof MergeReplaceKeyInterface) {
                $a[$key] = $value->getData();
            } elseif (isset($a[$key]) || array_key_exists($key, $a)) {
                if ($value instanceof MergeRemoveKey) {
                    unset($a[$key]);
                } elseif (is_int($key)) {
                    $a[] = $value;
                } elseif (is_array($value) && is_array($a[$key])) {
                    $a[$key] = $this->mergeArray($a[$key], $value);
                } else {
                    $a[$key] = $value;
                }
            } else {
                if (! $value instanceof MergeRemoveKey) {
                    $a[$key] = $value;
                }
            }
        }
        return $a;
    }
    /**
     * Merge configuration from a provider with existing configuration.
     *
     * @param array $mergedConfig Passed by reference as a performance/resource
     *     optimization.
     * @param mixed|array $config Configuration generated by the $provider.
     * @param callable $provider Provider responsible for generating $config;
     *     used for exception messages only.
     * @return void
     * @throws InvalidConfigProviderException
     */
    private function mergeConfig(&$mergedConfig, $config, $provider)
    {
        if (!is_callable($provider)) {
            throw new InvalidProviderException('Callable provider expected.');
        }

        if (! is_array($config)) {
            $type = '';
            if (is_object($provider) && ! $provider instanceof Closure) {
                $type = get_class($provider);
            }
            if ($provider instanceof Closure) {
                $type = 'Closure';
            }
            if (is_callable($provider) && ! $provider instanceof Closure) {
                $type = is_string($provider) ? $provider : gettype($provider);
            }
            throw new InvalidConfigProviderException(sprintf(
                'Cannot read config from %s; does not return array',
                $type
            ));
        }
        $mergedConfig = $this->mergeArray($mergedConfig, $config);
    }
    /**
     * Iterate providers, merging config from each with the previous.
     *
     * @param array $providers
     * @return array
     */
    private function loadConfigFromProviders(array $providers)
    {
        $mergedConfig = array();
        foreach ($providers as $provider) {
            $provider = $this->resolveProvider($provider);
            $config = $provider();
            if (! $config instanceof Generator) {
                $this->mergeConfig($mergedConfig, $config, $provider);
                continue;
            }
            // Handle generators
            foreach ($config as $cfg) {
                $this->mergeConfig($mergedConfig, $cfg, $provider);
            }
        }
        return $mergedConfig;
    }
    /**
     * Attempt to load the configuration from a cache file.
     *
     * @param null|string $cachedConfigFile
     * @return bool
     */
    private function loadConfigFromCache($cachedConfigFile)
    {
        if (null === $cachedConfigFile) {
            return false;
        }
        if (! file_exists($cachedConfigFile)) {
            return false;
        }
        $this->config = require $cachedConfigFile;
        return true;
    }
    /**
     * Attempt to cache discovered configuration.
     *
     * @param array $config
     * @param null|string $cachedConfigFile
     */
    private function cacheConfig(array $config, $cachedConfigFile)
    {
        if (null === $cachedConfigFile) {
            return;
        }
        if (empty($config[static::ENABLE_CACHE])) {
            return;
        }
        file_put_contents($cachedConfigFile, sprintf(
            self::CACHE_TEMPLATE,
            get_class($this),
            date('c'),
            var_export($config, true)
        ));
    }
}