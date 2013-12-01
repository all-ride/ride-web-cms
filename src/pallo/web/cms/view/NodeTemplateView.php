<?php

namespace pallo\web\cms\view;

use pallo\library\cms\layout\Layout;
use pallo\library\cms\node\Node;
use pallo\library\cms\theme\Theme;
use pallo\library\mvc\exception\MvcException;
use pallo\library\mvc\view\View;
use pallo\library\template\GenericThemedTemplate;

use pallo\web\mvc\view\TemplateView;

/**
 * Frontend view for a node
 */
class NodeTemplateView extends TemplateView {

    /**
     * Constructs a new template view
     * @param pallo\library\template\Template $template Instance of the
     * template to render
     * @return null
     */
    public function __construct(Node $node, Layout $layout, Theme $theme, $locale) {
        $template = new GenericThemedTemplate();
        $template->setResource('cms/frontend/layout.' . $layout->getName());
        $template->setResourceId($node->getId());
        $template->setTheme($theme->getName());
        $template->set('node', $node);
        $template->set('locale', $locale);

        parent::__construct($template);
    }

    /**
     * Set the dispatched views of the node to this view
     * @param array $dispatchedViews Array with region name as key and a widget
     * array as value. The widget array has the widget instance id as key and
     * the View of the widget as value.
     * @return null
     */
    public function setDispatchedViews(array $dispatchedViews) {
        $regions = array();
        foreach ($dispatchedViews as $regionName => $widgetViews) {
            $regions[$regionName] = array();

            foreach ($widgetViews as $widgetId => $widgetView) {
                if (!$widgetView) {
                    continue;
                }

                $this->processTemplateView($widgetView, $widgetId, $regionName);

                $regions[$regionName][$widgetId] = $widgetView;
            }
        }

        $this->template->set('regions', $regions);
    }

    public function setBreadcrumbs(array $breadcrumbs) {
        $this->template->set('breadcrumbs', $breadcrumbs);
    }

    /**
     * Process a widget view to add helpers
     * @param pallo\library\mvc\view\View $widgetView view of the widget
     * @param int $widgetId id of the widget
     * @param string $regionName name of the region
     * @return null
     */
    protected function processTemplateView(View $widgetView, $widgetId, $regionName) {
        if (!$widgetView instanceof TemplateView) {
            return;
        }

        $template = $widgetView->getTemplate();
        $template->setResourceId($widgetId);

        $app = $template->get('app', array());

        $app['cms'] = array(
            'widget' => $widgetId,
            'region' => $regionName,
            'node' => $this->template->get('node'),
            'breadcrumbs' => $this->template->get('breadcrumbs'),
        );

        $template->set('app', $app);
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
        $regions = $this->template->get('regions');
        foreach ($regions as $region => $widgets) {
            foreach ($widgets as $widgetId => $widgetView) {
                if ($widgetView instanceof TemplateView) {
                    $template = $widgetView->getTemplate();

                    $data = $template->get('app', array());
                    $app['cms'] = $data['cms'];

                    $template->set('app', $app);
                }

                $regions[$region][$widgetId] = $widgetView->render(true);
            }
        }
        $this->template->set('regions', $regions);

        $output = $this->templateFacade->render($this->template);

        if ($willReturnValue) {
            return $output;
        }

        echo $output;
    }

}