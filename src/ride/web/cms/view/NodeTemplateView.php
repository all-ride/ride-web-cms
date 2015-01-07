<?php

namespace ride\web\cms\view;

use ride\library\cms\node\Node;
use ride\library\cms\theme\Theme;
use ride\library\mvc\exception\MvcException;
use ride\library\mvc\view\HtmlView;
use ride\library\mvc\view\View;
use ride\library\template\GenericThemedTemplate;

use ride\web\mvc\view\TemplateView;

/**
 * Frontend view for a node
 */
class NodeTemplateView extends TemplateView {

    /**
     * Constructs a new template view
     * @param \ride\library\template\Template $template Instance of the
     * template to render
     * @return null
     */
    public function __construct(Node $node, Theme $theme, $locale) {
        $template = new GenericThemedTemplate();
        $template->setResource('cms/frontend/index');
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
     * Sets the context to the view
     * @param array $context
     * @return null
     */
    public function setContext(array $context) {
        $app = $this->template->get('app');
        $app['cms']['context'] = $context;

        $this->template->set('app', $app);
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
     * @param array $dispatchedViews Array with region name as key and a widget
     * array as value. The widget array has the widget instance id as key and
     * the View of the widget as value.
     * @return null
     */
    public function setDispatchedViews(array $dispatchedViews) {
        $this->template->set('widgets', $dispatchedViews);
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

        // render the widget templates in the regions
        $app = $this->template->get('app');

        $regions = $this->template->get('widgets');
        foreach ($regions as $this->region => $sections) {
            foreach ($sections as $this->section => $blocks) {
                foreach ($blocks as $this->block => $widgets) {
                    foreach ($widgets as $widgetId => $widgetView) {
                        if (!$widgetView) {
                            continue;
                        }

                        $regions[$this->region][$this->section][$this->block][$widgetId] = $this->renderWidget($widgetId, $widgetView, $app);
                    }
                }
            }
        }
        $this->template->set('widgets', $regions);

        return parent::render($willReturnValue);
    }

    /**
     * Handles the context and shared variables of the widget and renders it
     * @param string $widgetId Id of the widget
     * @param \ride\library\mvc\view\View $widgetView
     * @param array $app Common variables of the main template
     * @return string
     */
    protected function renderWidget($widgetId, View $widgetView, array $app) {
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

        return $widgetView->render(true);
    }

}
