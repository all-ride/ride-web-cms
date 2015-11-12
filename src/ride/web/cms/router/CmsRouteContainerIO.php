<?php

namespace ride\web\cms\router;

use ride\library\cms\expired\ExpiredRouteModel;
use ride\library\cms\node\type\ReferenceNodeType;
use ride\library\cms\node\NodeModel;
use ride\library\router\exception\RouterException;
use ride\library\router\RouteContainer;
use ride\library\router\Route;

use ride\web\router\io\RouteContainerIO;

/**
 * Route container I/O Implementation for the Joppa routes
 */
class CmsRouteContainerIO implements RouteContainerIO {

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
     * @param array $locales Array with the locale codes
     * @return null
     */
    public function __construct(NodeModel $nodeModel, ExpiredRouteModel $expiredRouteModel, array $locales) {
        $this->nodeModel = $nodeModel;
        $this->expiredRouteModel = $expiredRouteModel;
        $this->locales = $locales;
    }

    /**
     * Gets the route container from a data source
     * @return \ride\library\router\RouteContainer
     */
    public function getRouteContainer() {
        $container = new RouteContainer();

        $nodeTypeManager = $this->nodeModel->getNodeTypeManager();
        $nodeTypes = $nodeTypeManager->getNodeTypes();
        $defaultRevision = $this->nodeModel->getDefaultRevision();

        $registeredPaths = array();
        $expiredCallback = array('ride\\web\\cms\\controller\\frontend\\ExpiredController', 'indexAction');

        $sites = $this->nodeModel->getSites();
        foreach ($sites as $siteId => $site) {
            $nodes = $this->nodeModel->getNodes($siteId, $defaultRevision);

            foreach ($this->locales as $locale) {
                $baseUrl = $site->getBaseUrl($locale);

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

                    $route = new Route($path, $callback, 'cms.front.' . $siteId . '.' . $nodeId . '.' . $locale);
                    $route->setIsDynamic(true);
                    $route->setPredefinedArguments(array(
                        'site' => $siteId,
                        'node' => $nodeId,
                    ));
                    $route->setLocale($locale);

                    if ($baseUrl) {
                        $route->setBaseUrl($baseUrl);
                    }

                    $container->addRoute($route);

                    $registeredPaths[$path] = true;
                }
            }

            $expiredRoutes = $this->expiredRouteModel->getExpiredRoutes($siteId);
            foreach ($expiredRoutes as $expiredRoute) {
                $path = $expiredRoute->getPath();
                if (isset($registeredPaths[$path]) || $path == '/') {
                    continue;
                }

                $route = new Route($path, $expiredCallback);
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

                $container->addRoute($route);
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
