<?php

namespace ride\web\cms\controller\frontend;

use ride\library\cache\pool\CachePool;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\NodeModel;
use ride\library\cms\theme\ThemeModel;
use ride\library\cms\widget\WidgetModel;
use ride\library\event\EventManager;
use ride\library\http\Response;
use ride\library\log\Log;
use ride\library\mvc\view\View;
use ride\library\router\GenericRouter;
use ride\library\router\RouteContainer;
use ride\library\router\Route;

use ride\web\cms\node\dispatcher\NodeDispatcher;
use ride\web\cms\view\NodeTemplateView;
use ride\web\WebApplication;

/**
 * Controller of the CMS frontend
 */
class NodeController extends AbstractController {

	/**
     * Dispatches the frontend of a node
     * @return null
	 */
	public function indexAction(WebApplication $web, EventManager $eventManager, Log $log, LayoutModel $layoutModel, ThemeModel $themeModel, WidgetModel $widgetModel, NodeModel $nodeModel, $node, $locale = null) {
        $cache = null;

        $i18n = $this->getI18n();
        if ($locale === null) {
            $locale = $i18n->getLocale()->getCode();
        } else {
            $i18n->setCurrentLocale($locale);
        }

        $nodeDispatcher = $this->getNodeDispatcher($layoutModel, $themeModel, $widgetModel, $nodeModel, $node, $locale, $cache);
        if ($nodeDispatcher) {
            $node = $nodeDispatcher->getNode();
            if ($node->isPublished() && !$node->isAvailableInLocale($locale)) {
                $nodeDispatcher->setDispatcher($web->getDispatcher());
                $nodeDispatcher->setEventManager($eventManager);
                $nodeDispatcher->setLog($log);
                $nodeDispatcher->dispatch($this->request, $this->response, $this->getUser(), $cache);

                if ($this->response->getStatusCode() != Response::STATUS_CODE_NOT_FOUND) {
                    return;
                }
            }
        }

        // not found, try the public web controller
        return $this->chainWebRequest();
    }

    /**
     * Gets the dispatcher of a node
     * @param ride\library\cms\layout\LayoutModel $layoutModel Instance of the
     * layout model
     * @param ride\library\cms\theme\ThemeModel $themeModel Instance of the
     * theme model
     * @param ride\library\cms\widget\WidgetModel $widgetModel Instance of the
     * widget model
     * @param ride\library\cms\node\NodeModel $nodeModel Instance of the node
     * model
     * @param string $node Id of the node
     * @param string $locale Code of the current locale
     * @param ride\library\cache\pool\CachePool $cache Cache to store the
     * dispatcher
     * @return ride\web\cms\node\dispatcher\NodeDispatcher|null A node
     * dispatcher or null when no node could be found
     */
    private function getNodeDispatcher(LayoutModel $layoutModel, ThemeModel $themeModel, WidgetModel $widgetModel, NodeModel $nodeModel, $node, $locale, CachePool $cache = null) {
        if ($cache) {
            $cacheKey = 'node.dispatcher.' . $node . '.' . $locale;

            $cacheItem = $cache->get($cacheKey);
            if ($cacheItem->isValid()) {
                return $cacheItem->getValue();
            }
        }

        try {
            $node = $nodeModel->getNode($node);
        } catch (NodeNotFoundException $e) {
            return null;
        }

        $layout = $layoutModel->getLayout($node->getLayout($locale));
        $theme = $themeModel->getTheme($node->getTheme());

        $nodeView = new NodeTemplateView($node, $layout, $theme, $locale);
        $router = new GenericRouter(new RouteContainer());
        $breadcrumbs = $nodeModel->getBreadcrumbsForNode($node, $this->request->getBaseScript(), $locale);

        $nodeDispatcher = new NodeDispatcher($node, $nodeView, $router, $breadcrumbs);
        $nodeDispatcher->loadWidgets($widgetModel, $layout->getRegions() + $theme->getRegions());

        if ($cache) {
            $cacheItem->setValue($nodeDispatcher);

            $cache->set($cacheItem);
        }

        return $nodeDispatcher;
    }

}
