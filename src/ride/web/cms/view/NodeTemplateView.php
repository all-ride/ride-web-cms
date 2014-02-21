<?php

namespace ride\web\cms\view;

use ride\library\cms\layout\Layout;
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
     * @param ride\library\template\Template $template Instance of the
     * template to render
     * @return null
     */
    public function __construct(Node $node, Layout $layout, Theme $theme, $locale) {
        $template = new GenericThemedTemplate();
        $template->setResource('cms/frontend/layout.' . $layout->getName());
        $template->setResourceId($node->getId());
        $template->setTheme($theme->getName());
        $template->set('app', array(
            'cms' => array(
                'node' => $node,
            ),
            'locale' => $locale
        ));

        parent::__construct($template);
    }

    /**
     * Gets the node of this view
     * @return ride\library\cms\node\Node
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
     * Set the dispatched views of the node to this view
     * @param array $dispatchedViews Array with region name as key and a widget
     * array as value. The widget array has the widget instance id as key and
     * the View of the widget as value.
     * @return null
     */
    public function setDispatchedViews(array $dispatchedViews) {
        $this->template->set('regions', $dispatchedViews);
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

        $regions = $this->template->get('regions');
        foreach ($regions as $region => $widgets) {
            foreach ($widgets as $widgetId => $widgetView) {
                if (!$widgetView) {
                    continue;
                }

                if ($widgetView instanceof HtmlView) {
                    $this->mergeResources($widgetView);
                }

                if ($widgetView instanceof TemplateView) {
                    // merge main app template variable
                    $template = $widgetView->getTemplate();
                    $template->setResourceId($widgetId);
                    $template->setTheme($this->template->getTheme());

                    $widgetApp = $template->get('app');
                    $widgetApp['cms']['node'] = $app['cms']['node'];
                    $widgetApp['cms']['context'] = $app['cms']['context'];
                    $widgetApp['cms']['region'] = $region;
                    $widgetApp['cms']['widget'] = $widgetId;

                    $app['cms'] = $widgetApp['cms'];

                    $template->set('app', $app);

                    $widgetView->setTemplateFacade($this->templateFacade);
                }

                // render widget
                $regions[$region][$widgetId] = $widgetView->render(true);
            }
        }
        $this->template->set('regions', $regions);

        // render main template
        $output = $this->templateFacade->render($this->template);

        // return
        if ($willReturnValue) {
            return $output;
        }

        echo $output;
    }

}