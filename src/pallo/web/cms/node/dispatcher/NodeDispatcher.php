<?php

namespace pallo\web\cms\node\dispatcher;

use pallo\library\cache\pool\CachePool;
use pallo\library\cms\node\Node;
use pallo\library\cms\widget\Widget;
use pallo\library\cms\widget\WidgetModel;
use pallo\library\event\EventManager;
use pallo\library\mvc\dispatcher\Dispatcher;
use pallo\library\mvc\Request;
use pallo\library\mvc\Response;
use pallo\library\router\RouteContainer;
use pallo\library\router\Router;
use pallo\library\security\model\User;
use pallo\library\String;

use pallo\web\cms\view\NodeTemplateView;

/**
 * Dispatcher for the frontend of a node
 */
class NodeDispatcher {

    /**
     * Node which is to be dispatched
     * @var pallo\library\cms\node\Node
     */
    private $node;

    /**
     * View of the node
     * @var pallo\web\cms\view\NodeTemplateView
     */
    private $view;

    /**
     * Router for the widget routes
     * @var pallo\library\router\Router
     */
    private $router;

    /**
     * Dispatcher for the widgets
     * @var pallo\library\mvc\dispatcher\Dispatcher
     */
    private $dispatcher;

    /**
     * Instance of the event manager
     * @var pallo\library\event\EventManager
     */
    private $eventManager;

    /**
     * Instance of the log
     * @var pallo\library\log\Log
     */
    private $log;

    /**
     * Array with region name as key and a widget array as value. The widget
     * array has the widget id as key and the widget instance as value.
     * @var array
     */
    private $regions;

    /**
     * Breadcrumbs set by the node
     * @var array
     */
    private $nodeBreadcrumbs;

    /**
     * Breadcrumbs set by the widgets
     * @var array
     */
    private $widgetBreadcrumbs;

    /**
     * Construct the dispatcher
     * @param pallo\library\cms\node\Node $node
     * @param pallo\web\cms\view\NodeTemplateView $view View for the node
     * @param pallo\library\router\Router $router
     * @param array $breadcrumbs Array with the URL as key and the name as
     * value
     * @return null
     */
    public function __construct(Node $node, NodeTemplateView $view, Router $router, array $breadcrumbs) {
        $this->node = $node;
        $this->view = $view;
        $this->router = $router;
        $this->regions = array();
        $this->nodeBreadcrumbs = $breadcrumbs;
        $this->widgetBreadcrumbs = array();
    }

    /**
     * Get the node which is to be dispatched
     * @return pallo\library\cms\node\Node
     */
    public function getNode() {
        return $this->node;
    }

    /**
     * Sets the event manager
     * @param pallo\library\event\EventManager $eventManager
     * @return null
     */
    public function setEventManager(EventManager $eventManager) {
        $this->eventManager = $eventManager;
    }

    /**
     * Sets the MVC dispatcher
     * @param pallo\library\mvc\dispatcher\Dispatcher $dispatcher
     * @return null
     */
    public function setDispatcher(Dispatcher $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Sets the log
     * @param pallo\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Loads the widgets of the node for the provided regions
     * @param pallo\library\cms\widget\WidgetModel
     * @param array $regions Array with the name of the region as key and a
     * instance of Region as value
     * @return null
     */
    public function loadWidgets(WidgetModel $widgetModel, array $regions) {
        $this->regions = $regions;

        foreach ($this->regions as $regionName => $region) {
            $this->regions[$regionName] = $this->node->getWidgets($regionName);

            foreach ($this->regions[$regionName] as $widgetId => $widget) {
                $widget = clone $widgetModel->getWidget($widget);

                $this->regions[$regionName][$widgetId] = $widget;
            }
        }
    }

    /**
     * Dispatches the node
     * @param pallo\library\mvc\Request $request
     * @param pallo\library\mvc\Response $response
     * @param pallo\library\security\model\User $user
     * @param zibo\library\cache\pool\CachePool $cache
     * @return array Array with the region name as key and a view array as
     * value. The view array has the widget id as key and the dispatched
     * widget view as value
     */
    public function dispatch(Request $request, Response $response, User $user = null, CachePool $cache = null) {
        $route = $request->getRoute();

        $this->locale = $this->view->getTemplate()->get('locale');
        $this->routeArguments = $route->getArguments();

        unset($this->routeArguments['node']);
        unset($this->routeArguments['locale']);

        $route->setPredefinedArguments(array());
        $route->setArguments();
        $routeArgumentsMatched = false;

        if ($cache) {
            $method = $request->getMethod();

            $isCacheable = $method == 'GET' || $method == 'HEAD' ? true : false;
            $isNoCache = $request->isNoCache();

            if ($isCacheable) {
                $parameters = $this->routeArguments ? '-' . implode('-', $this->routeArguments) : '';
                $parameters .= $request->getQueryParametersAsString();

                $containsUserContent = false;

                $nodeCacheTtl = 0;
            }
        } else {
            $isCacheable = false;
        }

        $result = array();
        foreach ($this->regions as $regionName => $widgets) {
            $result[$regionName] = array();

            $this->region = $regionName;

            foreach ($widgets as $widgetId => $widget) {
                if ($this->log) {
                    $log->logDebug('Rendering widget ' . $widgetId . ' for region ' . $regionName, null, Module::LOG_SOURCE);
                }

                if ($isCacheable) {
                    $cacheKey = 'node.widget.view.' . $this->node->getId() . '.' . $regionName . '.' . $widgetId . '.' . $this->locale . $parameters;
                    if ($user) {
                        $cacheKey .= '-authenticated';
                    }
                }

                $widgetProperties = $this->node->getWidgetProperties($widgetId);

            	if ($isCacheable && !$isNoCache && !$widgetProperties->isCacheDisabled()) {
	                $cachedItem = $cache->get($cacheKey);
	                if ($cachedItem->isValid()) {
	                    $cacheView = $cachedItem->getValue();

	                    if ($cacheView->areRoutesMatched()) {
	                        $widgetMatchedRouteArguments = true;
	                    }

	                    if ($cacheView->getBreadcrumbs()) {
	                        $this->widgetBreadcrumbs = $cacheView->getBreadcrumbs();
	                    }

	                    if ($cacheView->isContent()) {
	                        $result = $cacheView->getView();

	                        break 2;
	                    } elseif ($cacheView->isRegion()) {
	                        $result[$regionName] = array($widgetId => $cacheView->getView());

	                        break;
	                    } else {
	                        $result[$regionName][$widgetId] = $cacheView->getView();

    	                    continue;
	                    }
	                }
            	}

                $widget->setProperties($widgetProperties);

                $widgetMatchedRouteArguments = $this->dispatchWidget($request, $response, $widgetId, $widget);
                if ($widgetMatchedRouteArguments) {
                    $routeArgumentsMatched = true;
                }

                $statusCode = $response->getStatusCode();
                if ($statusCode != Response::STATUS_CODE_OK && $statusCode != Response::STATUS_CODE_BAD_REQUEST) {
                    return;
                }

                $view = $response->getView();
                $response->setView(null);

                $isContent = $widget->isContent();
                $isRegion = $widget->isRegion();
                if ($isCacheable && !$containsUserContent && $widget->containsUserContent()) {
                    $containsUserContent = true;
                }

                $breadcrumbs = $widget->getBreadcrumbs();
                if ($breadcrumbs) {
                    $this->widgetBreadcrumbs = $breadcrumbs;
                }

                if ($isCacheable && !$widgetProperties->isCacheDisabled()) {
                    $cacheTtl = $widgetProperties->getCacheTtl();

                    $cachedItem = $cache->create($cacheKey);
                    $cachedItem->setValue(new WidgetCacheData($view, $isContent, $isRegion, $widgetMatchedRouteArguments));
                    $cachedItem->setTtl($cacheTtl);

                    if ($nodeCacheTtl !== false && $cacheTtl) {
                        if ($nodeCacheTtl == 0) {
                            $nodeCacheTtl = $cacheTtl;
                        } else {
                            $nodeCacheTtl = min($nodeCacheTtl, $cacheTtl);
                        }
                    }

                    $cache->set($cachedItem);
                } else {
                    $nodeCacheTtl = false;
                }

                if ($isContent) {
                    $result = $view;

                    break 2;
                }

                if ($isRegion) {
                    $result[$regionName] = array($widgetId => $view);

                    break;
                }

                $result[$regionName][$widgetId] = $view;
            }
        }

        if ($this->routeArguments && !$routeArgumentsMatched) {
            // sub route provided but never matched
            $response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            $result = null;
        }

        if ($isCacheable && $nodeCacheTtl !== false) {
            if ($user && $containsUserContent) {
                $isCacheable = false;
            }

            if ($isCacheable) {
                $this->cache = $cache;
                $this->cacheTtl = $nodeCacheTtl;

                $this->eventManager->addEventListener(WebApplication::EVENT_POST_RESPONSE, array($this, 'cacheResponse'));
            }
        }

        if (is_array($result)) {
            $breadcrumbs = $this->nodeBreadcrumbs + $this->widgetBreadcrumbs;

            $this->view->setBreadcrumbs($breadcrumbs);
            $this->view->setDispatchedViews($result);

            $response->setView($this->view);
        } else {
            $response->setView($result);
        }
    }

    /**
     * Caches the response
     * @param pallo\web\WebApplication $web
     * @return null
     */
    public function cacheResponse(WebApplication $web) {
        if (!isset($this->cache)) {
            return;
        }

        if (!isset($this->cacheTtl)) {
            $this->cacheTtl = 0;
        }

        $request = $web->getRequest();
        $response = $web->getResponse();

        $url = new String($request->getUrl());
        $cacheKey = 'node.response.' . $url->safeString();

        $log = $web->getLog();
        if ($this->log) {
            $log->logDebug('Caching the request', 'Ttl: ' . $this->cacheTtl . ' - ' . $cacheKey, 'cms');
        }

        $cachedResponse = $this->cache->create($cacheKey);
        $cachedResponse->setValue($response);
        $cachedResponse->setTtl($this->cacheTtl);

        $this->cache->set($cachedResponse);
    }

    /**
     * Dispatches a widget
     * @param pallo\library\mvc\Request $request
     * @param pallo\library\mvc\Response $response
     * @param integer $widgetId Id of the widget instance
     * @param pallo\library\cms\widget\Widget $widget Instance of the widget
     * @return null
     */
    protected function dispatchWidget(Request $request, Response $response, $widgetId, Widget $widget) {
        $routeArgumentsMatched = false;
        $callback = null;

        $widget->setIdentifier($widgetId);
        $widget->setLocale($this->locale);
        $widget->setRegion($this->region);

        $route = $request->getRoute();
        $route->setIsDynamic(false);

        $widgetRoutes = $widget->getRoutes();
        if ($widgetRoutes && $this->routeArguments) {
            $widgetRouteContainer = new RouteContainer();
            foreach ($widgetRoutes as $widgetRoute) {
                $widgetRouteContainer->addRoute($widgetRoute);
            }

            $path = '/' . implode('/', $this->routeArguments);

            $this->router->setRouteContainer($widgetRouteContainer);
            $routeResult = $this->router->route($request->getMethod(), $path, $widgetRoutes);
            $widgetRoute = $routeResult->getRoute();
            if ($widgetRoute) {
                $callback = $widgetRoute->getCallback();

                $route->setArguments($widgetRoute->getArguments());
                $route->setIsDynamic($widgetRoute->isDynamic());

                $routeArgumentsMatched = true;
            }
        }

        if (!$callback) {
            $callback = $widget->getCallback();
        }

        $route->setCallback($callback);

        $this->dispatcher->dispatch($request, $response);

        return $routeArgumentsMatched;
    }

}