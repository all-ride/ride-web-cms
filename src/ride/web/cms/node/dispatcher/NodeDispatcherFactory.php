<?php

namespace ride\web\cms\node\dispatcher;

use ride\library\cms\node\SiteNode;

/**
 * Interface to create a node dispatcher
 */
interface NodeDispatcherFactory {

    /**
     * Creates a node dispatcher for the provided node
     * @param \ride\library\cms\node\SiteNode $site
     * @param string $nodeId
     * @param string $baseUrl
     * @param string $locale
     * @return NodeDispatcher
     */
    public function createNodeDispatcher(SiteNode $site, $nodeId, $baseUrl, $locale);

}
