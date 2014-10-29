<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\layout\Layout;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\Node;
use ride\library\cms\theme\Theme;

use ride\web\cms\controller\backend\action\widget\WidgetActionManager;
use ride\web\cms\Cms;
use ride\web\mvc\controller\AbstractController;

/**
 * Controller of the layout node action
 */
class LayoutNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'layout';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.layout';

    /**
     * Detects the current region and redirects to the region page
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @return null
     */
    public function indexAction(Cms $cms, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $cms->setLastAction(self::NAME);

        $layout = null;
        if (method_exists($node, 'getLayout') && $layout = $node->getLayout($locale)) {
            $layout = $cms->getLayout($layout);
        }

        $theme = $node->getTheme();
        if ($theme) {
            $theme = $cms->getTheme($theme);
        }

        $form = $this->buildRegionForm($node, $cms->getLayoutModel(), $layout, $theme);
        if ($form->isSubmitted()) {
            $data = $form->getData();
            $region = $data['region'];
        } else {
            $region = $cms->getLastRegion($layout !== null);
        }

        if (!$region || ($region && (($layout && !$layout->hasRegion($region)) && !$theme->hasRegion($region)))) {
            if ($layout) {
                $regions = $layout->getRegions();
            } else {
                $regions = array();

                $layouts = $cms->getLayouts();
                foreach ($layouts as $layout) {
                    $regions += $layout->getRegions();
                }

                $regions += $theme->getRegions();
            }

            $region = array_shift($regions);
        }

        $this->response->setRedirect($this->getUrl('cms.node.layout.region', array(
            'locale' => $locale,
            'site' => $site->getId(),
            'revision' => $node->getRevision(),
            'node' => $node->getId(),
            'region' => $region,
        )));
    }

    /**
     * Action to show the region editor
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function regionAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region) {
        $theme = null;
        $layout = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme, $layout)) {
            return;
        }

        $regions = $theme->getRegions();
        $availableWidgets = $cms->getWidgets();
        $inheritedWidgets = $node->getInheritedWidgets($region);

        $cms->setLastAction(self::NAME);
        $cms->setLastRegion($region, isset($regions[$region]) ? 'theme' : 'layout');

        $form = $this->buildRegionForm($node, $cms->getLayoutModel(), $layout, $theme, $region);

        $regionWidgets = $node->getWidgets($region);
        foreach ($regionWidgets as $widgetId => $widget) {
            $widget = clone $availableWidgets[$widget];
            $widget->setIdentifier($widgetId);
            $widget->setProperties($node->getWidgetProperties($widgetId));
            $widget->setLocale($locale);

            if ($widget instanceof AbstractController) {
                $widget->setConfig($this->config);
                $widget->setDependencyInjector($this->dependencyInjector);
            }

            $regionWidgets[$widgetId] = $widget;
        }

        $baseAction = $this->getUrl('cms.node.layout.region', array(
            'locale' => $locale,
            'site' => $site->getId(),
            'revision' => $node->getRevision(),
            'node' => $node->getId(),
            'region' => $region,
        ));

        $this->setTemplateView('cms/backend/node.layout', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'availableWidgets' => $availableWidgets,
            'regionWidgets' => $regionWidgets,
            'inheritedWidgets' => $inheritedWidgets,
            'actions' => $widgetActionManager->getWidgetActions(),
            'baseAction' => $baseAction,
        ));
    }

    /**
     * Action to add a widget to the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $widget Id of the widget instance
     * @return null
     */
    public function widgetAddAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region, $widget) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $widgetId = $site->createWidget($widget);
        $node->addWidget($region, $widgetId);

        $cms->saveNode($site, 'Created new widget in ' . $site->getName());
        $cms->saveNode($node, 'Added widget to ' . $node->getName());

        $widget = clone $cms->getWidget($widget);
        $widget->setProperties($node->getWidgetProperties($widgetId));
        $widget->setLocale($locale);
        $widget->setRegion($region);

        if ($widget instanceof AbstractController) {
            $widget->setConfig($this->config);
            $widget->setDependencyInjector($this->dependencyInjector);
        }

        $this->setTemplateView('cms/backend/widget.content', array(
            'locale' => $locale,
            'site' => $site,
            'node' => $node,
            'region' => $region,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'actions' => $widgetActionManager->getWidgetActions(),
        ));
    }

    /**
     * Action to delete a widget from the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $widget Id of the widget instance
     * @return null
     */
    public function widgetDeleteAction(Cms $cms, $locale, $site, $revision, $node, $region, $widget) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $node->deleteWidget($region, $widget);

        $cms->saveNode($node, 'Deleted widget from ' . $node->getName());
    }

    /**
     * Action to reorder the widgets of the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function orderAction(Cms $cms, $locale, $site, $revision, $node, $region) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $widgetsValue = $this->request->getQueryParameter('widgets');
        $widgetsValue = rtrim(trim($widgetsValue),',');

        $node->orderWidgets($region, $widgetsValue);

        $cms->saveNode($node, 'Reordered widgets on ' . $node->getName());
    }

    /**
     * Creates a form to select the region
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\cms\layout\LayoutModel $layoutModel
     * @param \ride\library\cms\layout\Layout $layout
     * @param \ride\library\cms\theme\Theme $theme
     * @param string $region
     * @return \ride\library\form\Form
     */
    protected function buildRegionForm(Node $node, LayoutModel $layoutModel, Layout $layout = null, Theme $theme = null, $region = null) {
        $regions = array();

        if ($layout) {
            $regions['Layout'] = $layout->getRegions();
            if (!$regions['Layout']) {
                unset($regions['Layout']);
            }
        } else {
            $regions['Layout'] = array();
            $layouts = $layoutModel->getLayouts();
            foreach ($layouts as $layout) {
                $regions['Layout'] += $layout->getRegions();
            }
        }

        if ($theme) {
            $regions['Theme'] = $theme->getRegions();
            if (!$regions['Theme']) {
                unset($regions['Theme']);
            }
        }

        $form = $this->createFormBuilder(array('region' => $region));
        $form->setId('form-region-select');
        $form->addRow('region', 'select', array(
            'options' => $regions,
        ));

        return $form->build();
    }

}
