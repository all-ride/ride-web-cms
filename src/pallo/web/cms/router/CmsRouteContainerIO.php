<?php

namespace pallo\web\cms\router;

use pallo\library\cms\node\NodeModel;
use pallo\library\router\RouteContainer;
use pallo\library\router\Route;

use pallo\web\router\io\RouteContainerIO;

/**
 * Route container I/O Implementation for the Joppa routes
 */
class CmsRouteContainerIO implements RouteContainerIO {

    /**
     * Parent implementation
     * @var pallo\web\router\io\RouteContainerIO
     */
    private $io;

    /**
     * Instance of the node model
     * @var pallo\library\cms\node\NodeModel
     */
    private $nodeModel;

    /**
     * Available locales
     * @var array
     */
    private $locales;

    /**
     * Constructs a new Joppa route container I/O
     * @param pallo\web\router\io\RouteContainerIO $io Parent route container
     * I/O implementation
     * @param pallo\library\cms\node\NodeModel $nodeModel
     * @param array $locales Array with the locale codes
     * @return null
     */
    public function __construct(RouteContainerIO $io, NodeModel $nodeModel, array $locales) {
        $this->io = $io;
        $this->nodeModel = $nodeModel;
        $this->locales = $locales;
    }

    /**
     * Gets the route container from a data source
     * @return pallo\library\router\RouteContainer
     */
    public function getRouteContainer() {
        $container = $this->io->getRouteContainer();

        $nodeTypeManager = $this->nodeModel->getNodeTypeManager();
        $nodeTypes = $nodeTypeManager->getNodeTypes();

        $registeredPaths = array();

        $nodes = $this->nodeModel->getNodes();
        foreach ($nodes as $node) {
            if (!$node->getParent()) {
                continue;
            }

            $rootNode = $node->getRootNode();

            foreach ($this->locales as $locale) {
                $nodeType = $nodeTypes[$node->getType()];

                $callback = $nodeType->getFrontendCallback();
                if (!$callback) {
                    continue;
                }

                if (!$node->isAvailableInLocale($locale)) {
                    continue;
                }

                $path = $node->getRoute($locale, false);
                if (!$path) {
                    continue;
                }

                $route = new Route($path, $callback, 'cms.front.' . $node->getId() . '.' . $locale);
                $route->setIsDynamic($path != '/');
                $route->setPredefinedArguments(array('node' => $node->getId()));
                $route->setLocale($locale);

                $baseUrl = $rootNode->getBaseUrl($locale);
                if ($baseUrl) {
                    $route->setBaseUrl($baseUrl);
                }

                $container->addRoute($route);

                $registeredPaths[$path] = true;
            }
        }

//         $expiredRouteModel = $this->joppa->getExpiredRouteModel();

//         $expiredRoutes = $expiredRouteModel->getExpiredRoutes();
//         foreach ($expiredRoutes as $expiredRoute) {
//             if (isset($registeredPaths[$expiredRoute->getPath()])) {
//                 continue;
//             }

//             $callback = array('joppa\\controller\\frontend\\ExpiredController', 'indexAction');

//             $route = new Route($expiredRoute->getPath(), $callback);
//             $route->setIsDynamic(true);
//             $route->setPredefinedArguments(array('node' => $expiredRoute->getNode()));
//             $route->setLocale($expiredRoute->getLocale());

//             $baseUrl = $expiredRoute->getBaseUrl();
//             if ($baseUrl) {
//                 $route->setBaseUrl($baseUrl);
//             }

//             $container->addRoute($route);
//         }

        return $container;
    }

    /**
     * Sets the route container to the data source
     * @param zibo\library\router\RouteContainer;
     * @return null
     */
    public function setRouteContainer(RouteContainer $container) {
        $nodeTypeManager = $this->nodeModel->getNodeTypeManager();
        $nodeTypes = $nodeTypeManager->getNodeTypes();

        $nodes = $this->nodeModel->getNodes();
        foreach ($nodes as $node) {
            foreach ($this->locales as $locale) {
                $nodeType = $nodeTypes[$node->getType()];
                if (!$nodeType->getFrontendCallback()) {
                    continue;
                }

                $path = $node->getRoute($locale, false);
                if ($path) {
                    $container->removeRouteByPath($path);
                }
            }
        }

        parent::setRouteContainer($container);
    }

}