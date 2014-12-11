<?php

namespace ride\web\cms\node\dispatcher;

use ride\library\cache\pool\CachePool;
use ride\library\cms\exception\NodeNotFoundException;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\SiteNode;
use ride\library\cms\theme\ThemeModel;
use ride\library\cms\widget\WidgetModel;
use ride\library\event\EventManager;
use ride\library\log\Log;
use ride\library\mvc\dispatcher\Dispatcher;
use ride\library\router\GenericRouter;
use ride\library\router\RouteContainer;

use ride\web\cms\view\NodeTemplateView;

/**
 * Generic factory for a node dispatcher
 */
class GenericNodeDispatcherFactory implements NodeDispatcherFactory {

    /**
     * Model of the nodes
     * @var \ride\library\cms\node\NodeModel
     */
    protected $nodeModel;

    /**
     * Model of the themes
     * @var \ride\library\cms\theme\ThemeModel
     */
    protected $themeModel;

    /**
     * Model of the layouts
     * @var \ride\library\cms\layout\LayoutModel
     */
    protected $layoutModel;

    /**
     * Model of the widgets
     * @var \ride\library\cms\widget\WidgetModel
     */
    protected $widgetModel;

    /**
     * Instance of the MVC dispatcher
     * @var \ride\library\mvc\dispatcher\Dispatcher
     */
    protected $dispatcher;

    /**
     * Instance of the dispatcher cache
     * @var \ride\library\cache\CachePool
     */
    protected $cachePool;

    /**
     * Instance of the event manager
     * @var \ride\library\event\EventManager
     */
    protected $eventManager;

    /**
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

    /**
     * Constructs a new node dispatcher factory
     * @param \ride\library\cms\node\NodeModel $nodeModel
     * @param \ride\library\cms\theme\ThemeModel $themeModel
     * @param \ride\library\cms\widget\WidgetModel $widgetModel
     * @param \ride\library\mvc\dispatcher\Dispatcher $dispatcher
     * @return null
     */
    public function __construct(NodeModel $nodeModel, ThemeModel $themeModel, WidgetModel $widgetModel, Dispatcher $dispatcher) {
        $this->nodeModel = $nodeModel;
        $this->themeModel = $themeModel;
        $this->widgetModel = $widgetModel;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Sets the cache pool for the dispatcher
     * @param \ride\library\cache\CachePool $cachePool
     * @return null
     */
    public function setCachePool($cachePool) {
        $this->cachePool = $cachePool;
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
     * Sets the log
     * @param \ride\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Creates a node dispatcher for the provided node
     * @param \ride\library\cms\node\SiteNode $site
     * @param string $nodeId Id of the node
     * @param string $baseUrl
     * @param string $locale
     * @return \ride\web\cms\node\dispatcher\NodeDispatcher
     */
    public function createNodeDispatcher(SiteNode $site, $nodeId, $baseUrl, $locale) {
        if ($this->cachePool) {
            $cacheKey = 'node.dispatcher.' . $site->getId() . '.' . $nodeId . '.' . $locale;

            $cacheItem = $this->cachePool->get($cacheKey);
            if ($cacheItem->isValid()) {
                $nodeDispatcher = $cacheItem->getValue();

                $this->processNodeDispatcher($nodeDispatcher);

                return $nodeDispatcher;
            }
        }

        try {
            $node = $this->nodeModel->getNode($site->getId(), $site->getRevision(), $nodeId);
        } catch (NodeNotFoundException $exception) {
            return null;
        }

        $theme = $this->themeModel->getTheme($node->getTheme());

        $nodeView = new NodeTemplateView($node, $theme, $locale);
        $router = new GenericRouter(new RouteContainer());
        $breadcrumbs = $this->nodeModel->getBreadcrumbsForNode($node, $baseUrl, $locale);

        $nodeDispatcher = new GenericNodeDispatcher($node, $nodeView, $router, $breadcrumbs);
        $nodeDispatcher->loadWidgets($this->widgetModel, $theme->getRegions());

        if ($this->cachePool) {
            $cacheItem->setValue($nodeDispatcher);

            $this->cachePool->set($cacheItem);
        }

        $this->processNodeDispatcher($nodeDispatcher);

        return $nodeDispatcher;
    }

    /**
     * Process the node dispatcher after caching
     * @param NodeDispatcher
     * @return null
     */
    protected function processNodeDispatcher(NodeDispatcher $nodeDispatcher) {
        $nodeDispatcher->setDispatcher($this->dispatcher);

        if ($this->eventManager) {
            $nodeDispatcher->setEventManager($this->eventManager);
        }

        if ($this->log) {
            $nodeDispatcher->setLog($this->log);
        }
    }

}
