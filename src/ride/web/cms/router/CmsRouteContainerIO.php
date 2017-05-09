<?php

namespace ride\web\cms\router;

use ride\library\cms\expired\ExpiredRouteModel;
use ride\library\cms\node\type\HomeNodeType;
use ride\library\cms\node\type\ReferenceNodeType;
use ride\library\cms\node\NodeModel;
use ride\library\config\Config;
use ride\library\router\exception\RouterException;
use ride\library\router\RouteContainer;

use ride\web\router\io\RouteContainerIO;

/**
 * Route container I/O Implementation for the Joppa routes
 */
class CmsRouteContainerIO implements RouteContainerIO {

    /**
     * Source for the routes
     * @var string
     */
    const SOURCE = 'cms';

    /**
     * Instance of the node model
     * @var \ride\library\cms\node\NodeModel
     */
    private $nodeModel;

    /**
     * Instance of the expired route model
     * @var \ride\library\cms\expired\ExpiredRouteModel
     */
    private $expiredRouteModel;

    /**
     * Available locales
     * @var array
     */
    private $locales;

    /**
     * Constructs a new Joppa route container I/O
     * @param \ride\library\cms\node\NodeModel $nodeModel
     * @param \ride\library\cms\expired\ExpiredRouteModel $expiredRouteModel
     * @param \ride\library\cms\expired\ExpiredRouteModel $expiredRouteModel
     * @param array $locales Array with the locale codes
     * @return null
     */
    public function __construct(NodeModel $nodeModel, ExpiredRouteModel $expiredRouteModel, Config $config, array $locales) {
        $this->nodeModel = $nodeModel;
        $this->expiredRouteModel = $expiredRouteModel;
        $this->config = $config;
        $this->locales = $locales;
    }

    /**
     * Gets the route container from a data source
     * @return \ride\library\router\RouteContainer
     */
    public function getRouteContainer() {
        $container = new RouteContainer(self::SOURCE);

        $nodeTypeManager = $this->nodeModel->getNodeTypeManager();
        $nodeTypes = $nodeTypeManager->getNodeTypes();
        $defaultRevision = $this->nodeModel->getDefaultRevision();

        $registeredPaths = array();
        $expiredCallback = array('ride\\web\\cms\\controller\\frontend\\ExpiredController', 'indexAction');

        $home = null;

        $sites = $this->nodeModel->getSites();
        foreach ($sites as $siteId => $site) {
            $nodes = $this->nodeModel->getNodes($siteId, $defaultRevision);

            // look for an overriden base url of the site
            $siteBaseUrl = $this->config->get('cms.url.' . $siteId);
            if (!is_string($siteBaseUrl)) {
                $siteBaseUrl = null;
            }

            foreach ($this->locales as $locale) {
                // look for an overriden localized base url of the site
                $baseUrl = $this->config->get('cms.url.' . $siteId . '.' . $locale, $siteBaseUrl);
                if (!$baseUrl) {
                    // not overriden, check for the set base URL of the site
                    $baseUrl = $site->getBaseUrl($locale);
                }

                foreach ($nodes as $nodeId => $node) {
                    $type = $node->getType();
                    if (!$node->getParent() || $type === ReferenceNodeType::NAME) {
                        continue;
                    }

                    $nodeType = $nodeTypes[$type];

                    $callback = $nodeType->getFrontendCallback();
                    if (!$callback) {
                        continue;
                    }

                    if (!$node->isAvailableInLocale($locale)) {
                        continue;
                    }

                    $path = $node->getRoute($locale);
                    if (!$path) {
                        continue;
                    }

                    if ($home === null && $type === HomeNodeType::NAME) {
                        $home = $node;
                    }

                    $route = $container->createRoute($path, $callback, 'cms.front.' . $siteId . '.' . $nodeId . '.' . $locale);
                    $route->setIsDynamic(true);
                    $route->setPredefinedArguments(array(
                        'site' => $siteId,
                        'node' => $nodeId,
                    ));
                    $route->setLocale($locale);

                    if ($baseUrl) {
                        $route->setBaseUrl($baseUrl);
                    }

                    $container->setRoute($route);

                    $registeredPaths[$path] = true;
                }

                if ($home !== null && $home !== true) {
                    $nodeType = $nodeTypes[$home->getType()];
                    $callback = $nodeType->getFrontendCallback();

                    $route = $container->createRoute('/', $callback, 'cms.front.' . $siteId . '.' . $home->getId() . '.' . $locale);
                    $route->setIsDynamic(true);
                    $route->setPredefinedArguments(array(
                        'site' => $siteId,
                        'node' => $home->getId(),
                    ));
                    $route->setLocale($locale);

                    if ($baseUrl) {
                        $route->setBaseUrl($baseUrl);
                    }

                    $container->setRoute($route);

                    $home = true;
                }
            }

            $expiredRoutes = $this->expiredRouteModel->getExpiredRoutes($siteId);
            foreach ($expiredRoutes as $expiredRoute) {
                $path = $expiredRoute->getPath();
                if (isset($registeredPaths[$path]) || $path == '/') {
                    continue;
                }

                $route = $container->createRoute($path, $expiredCallback);
                $route->setIsDynamic(true);
                $route->setPredefinedArguments(array(
                    'site' => $siteId,
                    'node' => $expiredRoute->getNode(),
                ));
                $route->setLocale($expiredRoute->getLocale());

                $baseUrl = $expiredRoute->getBaseUrl();
                if ($baseUrl) {
                    $route->setBaseUrl($baseUrl);
                }

                $container->setRoute($route);
            }
        }

        return $container;
    }

    /**
     * Sets the route container to the data source
     * @param \ride\library\router\RouteContainer $routeContainer
     * @return null
     * @throws \ride\library\router\exception\RouterException
     */
    public function setRouteContainer(RouteContainer $routeContainer) {
        throw new RouterException('Could not set route container: not supported by this route container IO');
    }

}
