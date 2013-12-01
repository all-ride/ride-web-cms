<?php

namespace pallo\web\cms\controller\frontend;

use pallo\library\cache\pool\CachePool;
use pallo\library\cms\layout\LayoutModel;
use pallo\library\cms\node\NodeModel;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\cms\widget\WidgetModel;
use pallo\library\event\EventManager;
use pallo\library\http\Response;
use pallo\library\mvc\view\View;
use pallo\library\router\GenericRouter;
use pallo\library\router\RouteContainer;

use pallo\web\base\controller\AbstractController;
use pallo\web\cms\node\dispatcher\NodeDispatcher;
use pallo\web\cms\view\NodeTemplateView;
use pallo\web\WebApplication;

/**
 * Controller of the CMS frontend
 */
class NodeController extends AbstractController {

	/**
     * Dispatches the frontend of a node
     * @return null
	 */
	public function indexAction(WebApplication $web, EventManager $eventManager, LayoutModel $layoutModel, ThemeModel $themeModel, WidgetModel $widgetModel, NodeModel $nodeModel, $node, $locale = null) {
        $cache = null;

        $i18n = $this->getI18n();
        if ($locale === null) {
            $locale = $i18n->getLocale()->getCode();
        } else {
            $i18n->setCurrentLocale($locale);
        }

	    $nodeDispatcher = $this->getNodeDispatcher($layoutModel, $themeModel, $widgetModel, $nodeModel, $node, $locale, $cache);
	    if (!$nodeDispatcher) {
	        $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

	        return;
	    }

	    $node = $nodeDispatcher->getNode();
	    if (!$node->isPublished()) {
	        $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

	        return;
	    }

		$nodeDispatcher->setDispatcher($web->getDispatcher());
		$nodeDispatcher->setEventManager($eventManager);
		$nodeDispatcher->dispatch($this->request, $this->response, $this->getUser(), $cache);
	}

	/**
     * Gets the dispatcher of a node
     * @param pallo\library\cms\layout\LayoutModel $layoutModel Instance of the
     * layout model
     * @param pallo\library\cms\theme\ThemeModel $themeModel Instance of the
     * theme model
     * @param pallo\library\cms\widget\WidgetModel $widgetModel Instance of the
     * widget model
     * @param pallo\library\cms\node\NodeModel $nodeModel Instance of the node
     * model
     * @param string $node Id of the node
     * @param string $locale Code of the current locale
     * @param pallo\library\cache\pool\CachePool $cache Cache to store the
     * dispatcher
     * @return pallo\web\cms\node\dispatcher\NodeDispatcher|null A node
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