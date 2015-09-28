<?php

namespace ride\application\cache\control;

use ride\library\cache\pool\CachePool;
use ride\library\cms\node\io\NodeIO;
use ride\library\cms\node\io\CacheNodeIO;
use ride\library\config\Config;

/**
 * Cache control implementation for the CMS
 */
class CmsCacheControl extends AbstractCacheControl {

    /**
     * Name of this control
     * @var string
     */
    const NAME = 'cms';

    /**
     * Instance of the node IO
     * @var \ride\library\cms\node\io\NodeIO
     */
    private $io;

    /**
     * Instance of the configuration
     * @var \ride\library\config\Config
     */
    private $config;

    /**
     * Constructs a new CMS cache control
     * @param \ride\library\cms\node\io\NodeIO $io
     * @param \ride\library\config\Config $config
     * @return null
     */
    public function __construct(NodeIO $io, Config $config, CachePool $cache) {
        $this->io = $io;
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * Gets whether this cache can be enabled/disabled
     * @return boolean
     */
    public function canToggle() {
        return true;
    }

    /**
     * Enables this cache
     * @return null
     */
    public function enable() {
        $io = $this->config->get('cms.node.io.default', 'ini');
        if ($io == 'cache') {
            return;
        }

        $this->config->set('cms.node.io.cache', $io);
        $this->config->set('cms.node.io.default', 'cache');
    }

    /**
     * Disables this cache
     * @return null
     */
    public function disable() {
        $io = $this->config->get('cms.node.io.default', 'ini');
        if ($io != 'cache') {
            return;
        }

        $io = $this->config->get('cms.node.io.cache');

        $this->config->set('cms.node.io.default', $io);
        $this->config->set('cms.node.io.cache', null);
    }

    /**
     * Gets whether this cache is enabled
     * @return boolean
     */
    public function isEnabled() {
        return $this->io instanceof CacheNodeIO;
    }

    /**
     * Warms this cache
     * @return null
     */
    public function warm() {
        if ($this->isEnabled()) {
            $this->io->warmCache();
        }
    }

    /**
     * Clears this cache
     * @return null
     */
    public function clear() {
        $this->cache->flush();

        if ($this->isEnabled()) {
            $this->io->clearCache();
        }
    }

}
