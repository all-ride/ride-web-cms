<?php

namespace ride\web\cms\view;

use ride\library\cache\pool\CachePool;
use ride\library\cache\CacheItem;
use ride\library\cms\node\Node;
use ride\library\cms\theme\Theme;
use ride\library\event\EventManager;
use ride\library\mvc\exception\MvcException;
use ride\library\mvc\view\HtmlView;
use ride\library\mvc\view\View;
use ride\library\template\GenericThemedTemplate;

use ride\web\cms\ApplicationListener;
use ride\web\cms\Cms;
use ride\web\mvc\view\TemplateView;

use \Exception;

/**
 * Frontend view for a node
 */
class NodeTemplateView extends TemplateView {

    /**
     * Instance of the event manager
     * @var \ride\library\event\EventManager
     */
    private $eventManager;

    /**
     * Flag to see if debugging is enabled. When true, exceptions are thrown
     * instead of catched
     */
    private $isDebug;

    /**
     * Constructs a new template view
     * @param \ride\library\template\Template $template Instance of the
     * template to render
     * @return null
     */
    public function __construct(Node $node, Theme $theme, $locale) {
        $template = new GenericThemedTemplate();
        $template->setResource($node->get('template', 'cms/frontend/index'));
        $template->setResourceId($node->getId());
        $template->setTheme($theme->getName());
        $template->set('app', array(
            'cms' => array(
                'node' => $node,
                'site' => $node->getRootNodeId(),
            ),
            'locale' => $locale
        ));

        parent::__construct($template);

        $this->cache = null;
        $this->cacheItem = null;
        $this->cachedViews = null;
        $this->contentView = null;
    }

    /**
     * Sets whether debug is enabled
     * @param boolean $isDebug True to throw exceptions, false to catch them all
     * @return null
     */
    public function setIsDebug($isDebug) {
        $this->isDebug = $isDebug;
    }

    /**
     * Gets the node of this view
     * @return \ride\library\cms\node\Node
     */
    public function getNode() {
        $app = $this->template->get('app');

        return $app['cms']['node'];
    }

    /**
     * Gets the locale of this view
     * @return string Code of the locale
     */
    public function getLocale() {
        $app = $this->template->get('app');

        return $app['locale'];
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
     * Sets the available layouts to this view
     * @param array $layouts Array with the layout name as key and the layout
     * instance as value
     * @return null
     */
    public function setLayouts(array $layouts) {
        $this->template->set('layouts', $layouts);
    }

    /**
     * Sets the regions of the node to this view
     * @param array $regions Array with the name of the region as key and as
     * value an array with the section name as key and the layout name as value
     * @return null
     */
    public function setRegions(array $regions) {
        $this->template->set('regions', $regions);
    }

    /**
     * Set the dispatched views of the node to this view
     * @param array $dispatchedViews Array with region name as key and a section
     * array as value. A section array has the row id as key and a block array
     * as value. A block array has the block id as key and a widget array as
     * value. A widget array has the widget instance id as key and the View of
     * the widget as value.
     * @return null
     */
    public function setDispatchedViews(array $dispatchedViews) {
        $this->template->set('widgets', $dispatchedViews);
    }

    /**
     * Sets the cached widget views to this view
     * @param \ride\library\cache\pool\CachePool $cache Instance of the CMS
     * cache to store updates of the views
     * @param \ride\library\cache\CacheItem $cacheItem Cache item containing the
     * cached views. This is an array with the structure of dispatchedViews but
     * always with a WidgetCacheView as value
     * @return null
     * @see WidgetCacheView
     */
    public function setCachedViews(CachePool $cache, CacheItem $cacheItem) {
        $this->cache = $cache;
        $this->cacheItem = $cacheItem;
        $this->cachedViews = $cacheItem->getValue();
    }

    /**
     * Sets the view representing the content
     * @param \ride\library\mvc\view\View $view
     * @return null
     */
    public function setContentView(View $view, $widgetId, $block, $section, $region) {
        $this->contentView = $view;
        $this->contentViewId = $widgetId;
        $this->contentViewBlock = $block;
        $this->contentViewSection = $section;
        $this->contentViewRegion = $region;
    }

    /**
     * Renders the output for this view
     * @param boolean $willReturnValue True to return the rendered view, false
     * to send it straight to the client
     * @return null|string Null when provided $willReturnValue is set to true, the
     * rendered output otherwise
     */
    public function render($willReturnValue = true) {
        if (!$this->templateFacade) {
            throw new MvcException("Could not render template view: template facade not set, invoke setTemplateFacade() first");
        }

        $app = $this->template->get('app');
        $app['cms']['context'] = $app['cms']['node']->getContext();

        $this->template->set('app', $app);

        // single content view
        if ($this->contentView) {
            $this->block = $this->contentViewBlock;
            $this->section = $this->contentViewSection;
            $this->region = $this->contentViewRegion;

            return $this->renderWidget($this->contentViewId, $this->contentView, $app, $willReturnValue);
        }

        // render the widget templates in the regions
        $regions = $this->template->get('widgets');
        if ($regions) {
            foreach ($regions as $this->region => $sections) {
                foreach ($sections as $this->section => $blocks) {
                    foreach ($blocks as $this->block => $widgets) {
                        foreach ($widgets as $widgetId => $widgetView) {
                            if (!$widgetView) {
                                continue;
                            }

                            // render the widget
                            try {
                                $renderedWidget = $this->renderWidget($widgetId, $widgetView, $app);
                            } catch (Exception $exception) {
                                if ($this->isDebug) {
                                    throw $exception;
                                }

                                if ($this->eventManager) {
                                    $this->eventManager->triggerEvent(ApplicationListener::EVENT_WIDGET_EXCEPTION, array(
                                        'exception' => $exception,
                                        'widgetId' => $widgetId,
                                    ));
                                }

                                if ($app['security']->isPermissionGranted(Cms::PERMISSION_ERROR)) {
                                    $errorView = new WidgetErrorTemplateView($this->template->getTheme(), $this->region, $this->section, $this->block, $widgetId, $app['cms']['node']->getRootNode()->get('widget.' . $widgetId), $exception);
                                    $errorView->setTemplateFacade($this->templateFacade);

                                    $renderedWidget = $errorView->render(true);
                                } else {
                                    $renderedWidget = '';
                                }
                            }

                            if ($this->cache && isset($this->cachedViews[$this->region][$this->section][$this->block][$widgetId])) {
                                // cache the rendered view
                                $widgetCacheView = new WidgetCacheView($renderedWidget);
                                if ($widgetView instanceof HtmlView) {
                                    $widgetCacheView->mergeResources($widgetView);
                                }

                                $this->cachedViews[$this->region][$this->section][$this->block][$widgetId]->setView($widgetCacheView);
                            }

                            if (trim($renderedWidget) == '') {
                                // omit empty widgets
                                unset($regions[$this->region][$this->section][$this->block][$widgetId]);
                            } else {
                                $regions[$this->region][$this->section][$this->block][$widgetId] = $renderedWidget;
                            }
                        }

                        // omit empty blocks
                        if (!$regions[$this->region][$this->section][$this->block]) {
                            unset($regions[$this->region][$this->section][$this->block]);
                        }
                    }

                    // omit empty sections
                    if (!$regions[$this->region][$this->section]) {
                        unset($regions[$this->region][$this->section]);
                    }
                }

                // omit empty regions
                if (!$regions[$this->region]) {
                    unset($regions[$this->region]);
                }
            }
        }

        if ($this->cache) {
            $this->cacheItem->setValue($this->cachedViews);
            $this->cache->set($this->cacheItem);
        }

        $this->template->set('widgets', $regions);

        return parent::render($willReturnValue);
    }

    /**
     * Handles the context and shared variables of the widget and renders it
     * @param string $widgetId Id of the widget
     * @param \ride\library\mvc\view\View $widgetView
     * @param array $app Common variables of the main template
     * @param boolean $willReturnValue True to return the rendered view, false
     * to send it straight to the client
     * @return string
     */
    protected function renderWidget($widgetId, View $widgetView, array $app, $willReturnValue = true) {
        if ($widgetView instanceof HtmlView) {
            $this->mergeResources($widgetView);
        }

        if ($widgetView instanceof TemplateView) {
            // merge main app template variable
            $template = $widgetView->getTemplate();
            $template->setResourceId($widgetId);
            $template->setTheme($this->template->getTheme());

            $widgetApp = $template->get('app');
            $widgetApp['cms']['site'] = $app['cms']['site'];
            $widgetApp['cms']['node'] = $app['cms']['node'];
            $widgetApp['cms']['context'] = $app['cms']['context'];
            $widgetApp['cms']['region'] = $this->region;
            $widgetApp['cms']['section'] = $this->section;
            $widgetApp['cms']['block'] = $this->block;
            $widgetApp['cms']['widget'] = $widgetId;
            $widgetApp['cms']['properties'] = $app['cms']['node']->getWidgetProperties($widgetId);

            $app['cms'] = $widgetApp['cms'];

            $template->set('app', $app);

            $widgetView->setTemplateFacade($this->templateFacade);
        }

        return $widgetView->render($willReturnValue);
    }

}
