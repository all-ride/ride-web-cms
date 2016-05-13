<?php

namespace ride\web\cms\node\dispatcher;

use ride\library\cache\pool\CachePool;
use ride\library\cms\node\Node;
use ride\library\cms\widget\Widget;
use ride\library\cms\widget\WidgetModel;
use ride\library\event\EventManager;
use ride\library\log\Log;
use ride\library\mvc\dispatcher\Dispatcher;
use ride\library\mvc\Request;
use ride\library\mvc\Response;
use ride\library\router\RouteContainer;
use ride\library\router\Router;
use ride\library\security\SecurityManager;
use ride\library\StringHelper;

use ride\web\cms\view\NodeTemplateView;
use ride\web\cms\ApplicationListener;

/**
 * Dispatcher for the frontend of a node
 */
class GenericNodeDispatcher implements NodeDispatcher {

    /**
     * Node which is to be dispatched
     * @var \ride\library\cms\node\Node
     */
    private $node;

    /**
     * View of the node
     * @var \ride\web\cms\view\NodeTemplateView
     */
    private $view;

    /**
     * Router for the widget routes
     * @var \ride\library\router\Router
     */
    private $router;

    /**
     * Dispatcher for the widgets
     * @var \ride\library\mvc\dispatcher\Dispatcher
     */
    private $dispatcher;

    /**
     * Instance of the event manager
     * @var \ride\library\event\EventManager
     */
    private $eventManager;

    /**
     * Instance of the log
     * @var \ride\library\log\Log
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
    private $breadcrumbs;

    /**
     * Construct the dispatcher
     * @param \ride\library\cms\node\Node $node
     * @param \ride\web\cms\view\NodeTemplateView $view View for the node
     * @param \ride\library\router\Router $router
     * @param array $breadcrumbs Array with the URL as key and the name as
     * value
     * @return null
     */
    public function __construct(Node $node, NodeTemplateView $view, Router $router, array $breadcrumbs) {
        $this->node = $node;
        $this->view = $view;
        $this->router = $router;
        $this->regions = array();
        $this->breadcrumbs = $breadcrumbs;
    }

    /**
     * Get the node which is to be dispatched
     * @return \ride\library\cms\node\Node
     */
    public function getNode() {
        return $this->node;
    }

    /**
     * Gets the view
     * @return \ride\web\cms\view\NodeTemplateView
     */
    public function getView() {
        return $this->view;
    }

    /**
     * Sets the event manager
     * @param \ride\library\event\EventManager $eventManager
     * @return null
     */
    public function setEventManager(EventManager $eventManager) {
        $this->eventManager = $eventManager;
    }

    /**
     * Sets the MVC dispatcher
     * @param \ride\library\mvc\dispatcher\Dispatcher $dispatcher
     * @return null
     */
    public function setDispatcher(Dispatcher $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Sets the log
     * @param \ride\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Loads the widgets of the node for the provided regions
     * @param ride\library\cms\widget\WidgetModel
     * @param array $regions Array with the name of the region as key and a
     * instance of Region as value
     * @return null
     */
    public function loadWidgets(WidgetModel $widgetModel, array $regions) {
        $this->regions = array();
        $this->widgets = array();

        foreach ($regions as $region => $null) {
            $this->widgets[$region] = array();

            $this->regions[$region] = $this->node->getSections($region);
            foreach ($this->regions[$region] as $section => $layout) {
                $this->widgets[$region][$section] = $this->node->getWidgets($region, $section);
                foreach ($this->widgets[$region][$section] as $block => $widgets) {
                    foreach ($widgets as $widgetId => $widget) {
                        $widget = $widgetModel->getWidget($widget);
                        if ($widget) {
                            $this->widgets[$region][$section][$block][$widgetId] = clone $widget;
                        } else {
                            unset($this->widgets[$region][$section][$block][$widgetId]);
                        }
                    }

                    if (!$this->widgets[$region][$section][$block]) {
                        unset($this->widgets[$region][$section][$block]);
                    }
                }

                if (!$this->widgets[$region][$section]) {
                    unset($this->widgets[$region][$section]);
                    unset($this->regions[$region][$section]);
                }
            }

            if (!$this->widgets[$region]) {
                unset($this->widgets[$region]);
                unset($this->regions[$region]);
            }
        }
    }

    /**
     * Dispatches the node
     * @param \ride\library\mvc\Request $request
     * @param \ride\library\mvc\Response $response
     * @param \ride\library\security\SecurityManager $securityManager
     * @param \ride\library\cache\pool\CachePool $cache
     * @return array Array with the region name as key and a view array as
     * value. The view array has the widget id as key and the dispatched
     * widget view as value
     */
    public function dispatch(Request $request, Response $response, SecurityManager $securityManager, CachePool $cache = null) {
        $this->locale = $this->view->getLocale();

        // initialize context
        $context = array(
            'title' => array(
                'site' => $this->node->getRootNode()->getName($this->locale, 'title'),
                'node' => $this->node->getName($this->locale, 'title'),
            ),
            'breadcrumbs' => $this->breadcrumbs,
            'styles' => array(),
            'scripts' => array(),
        );

        // prepare and process incoming route arguments
        $route = $request->getRoute();
        $this->routeArguments = $route->getArguments();

        unset($this->routeArguments['node']);
        unset($this->routeArguments['locale']);
        if (isset($this->routeArguments['site'])) {
            // preview has site and revision
            unset($this->routeArguments['site']);
            unset($this->routeArguments['revision']);
        }

        $nodeRoute = $this->node->getRoute($this->locale);
        $nodeRouteTokens = explode('/', ltrim($nodeRoute, '/'));
        foreach ($nodeRouteTokens as $tokenIndex => $tokenValue) {
            if (isset($this->routeArguments[$tokenIndex]) && $this->routeArguments[$tokenIndex] === $tokenValue) {
                unset($this->routeArguments[$tokenIndex]);
            }
        }

        $route->setPredefinedArguments(array());
        $route->setArguments();
        $routeArgumentsMatched = false;

        // check for cache
        $cacheKey = null;
        $cacheItem = null;
        $cachedViews = array();
        $dispatchedViews = array();
        $nodeCacheTtl = false;
        $containsUserContent = false;

        if ($cache) {
            $method = $request->getMethod();

            $isCacheable = $method == 'GET' || $method == 'HEAD' ? true : false;
            $isNoCache = $request->isNoCache();

            if ($isCacheable) {
                $parameters = $this->routeArguments ? '-' . implode('-', $this->routeArguments) : '';
                $parameters .= $request->getQueryParametersAsString();

                $nodeCacheTtl = 0;

                $cacheKey = 'node.view.' . $this->node->getId() . '.' . $this->node->getRevision() . '.' . $this->locale . '.' . substr(md5($parameters), 0, 10);
                if ($securityManager->getUser()) {
                    $cacheKey .= '.authenticated';
                }

                $cacheItem = $cache->get($cacheKey);
                if ($cacheItem->isValid()) {
                    $cachedViews = $cacheItem->getValue();
                } else {
                    $cachedViews = array();
                }
            }
        } else {
            $isCacheable = false;
        }

        foreach ($this->widgets as $this->region => $sections) {
            $dispatchedViews[$this->region] = array();

            foreach ($sections as $this->section => $blocks) {
                $dispatchedViews[$this->region][$this->section] = array();

                foreach ($blocks as $this->block => $widgets) {
                    $dispatchedViews[$this->region][$this->section][$this->block] = array();

                    foreach ($widgets as $widgetId => $widget) {
                        if ($this->log) {
                            $this->log->logDebug('Rendering widget ' . $widget->getName() . '#' . $widgetId . ' for region ' . $this->region, null, ApplicationListener::LOG_SOURCE);
                        }

                        $widgetProperties = $this->node->getWidgetProperties($widgetId);
                        if (!$widgetProperties->isPublished()) {
                            if ($this->log) {
                                $this->log->logDebug('Widget ' . $widget->getName() . '#' . $widgetId . ' is not published', null, ApplicationListener::LOG_SOURCE);
                            }

                            continue;
                        } elseif (!$widgetProperties->isAvailableInLocale($this->locale)) {
                            if ($this->log) {
                                $this->log->logDebug('Widget ' . $widget->getName() . '#' . $widgetId . ' is not available in locale ' . $this->locale, null, ApplicationListener::LOG_SOURCE);
                            }

                            continue;
                        } elseif (!$widgetProperties->isAllowed($securityManager)) {
                            if ($this->log) {
                                $this->log->logDebug('Widget ' . $widget->getName() . '#' . $widgetId . ' is not allowed', null, ApplicationListener::LOG_SOURCE);
                            }

                            continue;
                        }

                        if ($isCacheable) {
                            $widgetCacheKey = $this->region . '.' . $this->section . '.' . $this->block . '.' . $widgetId . '.';
                        }

                        $isWidgetCache = $widgetProperties->isCacheEnabled() || ($widgetProperties->isAutoCache() && $widget->isAutoCache());
                        if ($isCacheable && !$isNoCache && $isWidgetCache) {
                            if (isset($cachedViews[$this->region][$this->section][$this->block][$widgetId])) {
                                if ($this->log) {
                                    $this->log->logDebug('Retrieved widget ' . $widget->getName() . '#' . $widgetId . ' from cache', null, ApplicationListener::LOG_SOURCE);
                                }

                                $cacheView = $cachedViews[$this->region][$this->section][$this->block][$widgetId];

                                if ($cacheView->areRoutesMatched()) {
                                    $widgetMatchedRouteArguments = true;
                                }

                                $cacheContext = $cacheView->getContext();
                                if ($cacheContext) {
                                    foreach ($cacheContext as $key => $value) {
                                        if ($value !== null) {
                                            $context[$key] = $value;
                                        } elseif (isset($context[$key])) {
                                            unset($context[$key]);
                                        }
                                    }
                                }

                                if ($cacheView->isContent()) {
                                    $dispatchedViews = null;
                                    $this->view->setContentView($view, $widgetId, $this->block, $this->section, $this->region);

                                    break 4;
                                } elseif ($cacheView->isRegion()) {
                                    $dispatchedViews[$this->region] = array($this->section => array($this->block => array($widgetId => $cacheView->getView())));

                                    break 3;
                                } elseif ($cacheView->isSection()) {
                                    $dispatchedViews[$this->region][$this->section] = array($this->block => array($widgetId => $cacheView->getView()));

                                    break 2;
                                } elseif ($cacheView->isBlock()) {
                                    $dispatchedViews[$this->region][$this->section][$this->block] = array($widgetId => $cacheView->getView());

                                    break;
                                } else {
                                    $dispatchedViews[$this->region][$this->section][$this->block][$widgetId] = $cacheView->getView();

                                    continue;
                                }
                            }
                        }

                        $widget->setProperties($widgetProperties);
                        $widget->setContext($context);

                        $widgetMatchedRouteArguments = $this->dispatchWidget($request, $response, $widgetId, $widget);
                        if ($widgetMatchedRouteArguments) {
                            $routeArgumentsMatched = true;
                        }

                        $statusCode = $response->getStatusCode();
                        if ($statusCode != Response::STATUS_CODE_OK && $statusCode != Response::STATUS_CODE_BAD_REQUEST && $statusCode != Response::STATUS_CODE_UNPROCESSABLE_ENTITY) {
                            return;
                        }

                        $view = $response->getView();
                        $response->setView(null);

                        $isContent = $widget->isContent();
                        $isRegion = $widget->isRegion();
                        $isSection = $widget->isSection();
                        $isBlock = $widget->isBlock();
                        if (!$containsUserContent && $widget->containsUserContent()) {
                            $containsUserContent = true;
                        }

                        $oldContext = $context;
                        $context = $widget->getContext();

                        if ($isCacheable && $isWidgetCache) {
                            $widgetContext = $this->getContextDifference($context, $oldContext);
                            if (!$widgetContext) {
                                // calculate node cache time based on the least widget cache time
                                $cacheTtl = $widgetProperties->getCacheTtl();
                                if ($nodeCacheTtl !== false && $cacheTtl) {
                                    if ($nodeCacheTtl == 0) {
                                        $nodeCacheTtl = $cacheTtl;
                                    } else {
                                        $nodeCacheTtl = min($nodeCacheTtl, $cacheTtl);
                                    }
                                }

                                $widgetCachedView = new WidgetCacheData($widgetContext, $isContent, $isRegion, $isSection, $isBlock, $widgetMatchedRouteArguments);

                                $cachedViews[$this->region][$this->section][$this->block][$widgetId] = $widgetCachedView;
                            }
                        }

                        if ($isContent) {
                            $dispatchedViews = null;
                            $this->view->setContentView($view, $widgetId, $this->block, $this->section, $this->region);

                            break 4;
                        } elseif ($isRegion) {
                            $dispatchedViews[$this->region] = array($this->section => array($this->block => array($widgetId => $view)));

                            break 3;
                        } elseif ($isSection) {
                            $dispatchedViews[$this->region][$this->section] = array($this->block => array($widgetId => $view));

                            break 2;
                        } elseif ($isBlock) {
                            $dispatchedViews[$this->region][$this->section][$this->block] = array($widgetId => $view);

                            break;
                        }

                        $dispatchedViews[$this->region][$this->section][$this->block][$widgetId] = $view;
                    }

                    if (!$dispatchedViews[$this->region][$this->section][$this->block]) {
                        unset($dispatchedViews[$this->region][$this->section][$this->block]);
                    }
                }

                if (!$dispatchedViews[$this->region][$this->section]) {
                    unset($dispatchedViews[$this->region][$this->section]);
                }
            }

            if (!$dispatchedViews[$this->region]) {
                unset($dispatchedViews[$this->region]);
            }
        }

        if ($this->routeArguments && !$routeArgumentsMatched) {
            // sub route provided but never matched
            $response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
            $response->setView(null);

            $dispatchedViews = null;
        }

        // if ($this->eventManager && $isCacheable && $nodeCacheTtl !== false) {
            // if ($user && $containsUserContent) {
                // $isCacheable = false;
            // }

            // if ($isCacheable) {
                // $this->cache = $cache;
                // $this->cacheTtl = $nodeCacheTtl;

                // $this->eventManager->addEventListener(WebApplication::EVENT_POST_RESPONSE, array($this, 'cacheResponse'));
            // }
        // }

        if (is_array($dispatchedViews)) {
            $this->view->setDispatchedViews($dispatchedViews);
            $this->view->setRegions($this->regions);

            if ($this->node->get('cache.target') == 'none') {
                $response->setIsNoStore();
            } elseif (!$containsUserContent) {
                $response->setIsPublic();
            } else {
                $response->setIsPrivate();
            }

            if ($nodeCacheTtl !== false && $cachedViews) {
                $cacheItem->setValue($cachedViews);
                $cacheItem->setTtl($nodeCacheTtl);

                $this->view->setCachedViews($cache, $cacheItem);
            }
        }

        $response->setView($this->view);
    }

    /**
     * Caches the response
     * @param \ride\web\WebApplication $web
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

        $cacheKey = 'node.response.' . StringHelper::safeString($request->getUrl());

        if ($this->log) {
            $this->log->logDebug('Caching the request', 'Ttl: ' . $this->cacheTtl . ' - ' . $cacheKey, ApplicationListener::LOG_SOURCE);
        }

        $cachedResponse = $this->cache->create($cacheKey);
        $cachedResponse->setValue($response);
        $cachedResponse->setTtl($this->cacheTtl);

        $this->cache->set($cachedResponse);
    }

    /**
     * Dispatches a widget
     * @param \ride\library\mvc\Request $request
     * @param \ride\library\mvc\Response $response
     * @param integer $widgetId Id of the widget instance
     * @param \ride\library\cms\widget\Widget $widget Instance of the widget
     * @return null
     */
    protected function dispatchWidget(Request $request, Response $response, $widgetId, Widget $widget) {
        $routeArgumentsMatched = false;
        $callback = null;

        $widget->setIdentifier($widgetId);
        $widget->setLocale($this->locale);
        $widget->setRegion($this->region);
        $widget->setSection($this->section);
        $widget->setBlock($this->block);

        $route = $request->getRoute();
        $route->setIsDynamic(false);

        $widgetRoutes = $widget->getRoutes();
        if ($widgetRoutes && $this->routeArguments) {
            $widgetRouteContainer = new RouteContainer();
            foreach ($widgetRoutes as $widgetRoute) {
                $widgetRouteContainer->setRoute($widgetRoute);
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

        if (!$routeArgumentsMatched) {
            $route->setArguments(array());
            $route->setIsDynamic(false);
        }

        if (!$callback) {
            $callback = $widget->getCallback();
        }

        $route->setCallback($callback);

        $this->dispatcher->dispatch($request, $response);

        return $routeArgumentsMatched;
    }

    /**
     * Gets the new or updated values of the context
     * @param array $context Current context
     * @param array $oldContext Previous context
     * @return array
     */
    protected function getContextDifference(array $context, array $oldContext) {
        $result = array();

        foreach ($context as $key => $value) {
            if (!isset($oldContext[$key])) {
                $result[$key] = $value;
            } elseif ($oldContext[$key] !== $value) {
                $result[$key] = $value;
            }
        }

        foreach ($oldContext as $key => $value) {
            if (!isset($context[$key])) {
                $result[$key] = null;
            }
        }

        return $result;
    }

}
