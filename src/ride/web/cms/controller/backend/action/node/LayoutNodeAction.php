<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\layout\Layout;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\cms\theme\Theme;
use ride\library\cms\theme\ThemeModel;
use ride\library\cms\widget\WidgetModel;
use ride\library\i18n\I18n;

use ride\web\cms\controller\backend\action\widget\WidgetActionManager;
use ride\web\mvc\controller\AbstractController;

/**
 * Controller of the layout node action
 */
class LayoutNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'layout';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.layout';

    /**
     * Session key for the last region
     * @var string
     */
    const SESSION_LAST_REGION = 'cms.region.last.';

    /**
     * Detects the current region and redirects to the region page
     * @return null
     */
    public function indexAction($locale, ThemeModel $themeModel, LayoutModel $layoutModel, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $layout = null;
        if (method_exists($node, 'getLayout') && $layout = $node->getLayout($locale)) {
            $layout = $layoutModel->getLayout($layout);
        }

        $theme = $node->getTheme();
        if ($theme) {
            $theme = $themeModel->getTheme($theme);
        }

        $form = $this->createRegionForm($node, $layoutModel, $layout, $theme);
        if ($form->isSubmitted()) {
            $data = $form->getData();
            $region = $data['region'];
        } else {
            $region = $this->getLastRegion($layout !== null);
        }

        if (!$region || ($region && (($layout && !$layout->hasRegion($region)) && !$theme->hasRegion($region)))) {
            if ($layout) {
                $regions = $layout->getRegions();
            } else {
                $regions = array();

                $layouts = $layoutModel->getLayouts();
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
            'node' => $node->getId(),
            'region' => $region,
        )));
    }

    /**
     * Action to show the region editor
     * @return null
     */
    public function regionAction(I18n $i18n, $locale, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, WidgetActionManager $widgetActionManager, NodeModel $nodeModel, $site, $node, $region) {
        $theme = null;
        $layout = null;
        if (!$this->resolveNode($nodeModel, $site, $node) || !$this->resolveRegion($themeModel, $layoutModel, $node, $locale, $region, $theme, $layout)) {
            return;
        }

        $regions = $theme->getRegions();

        $this->setLastAction(self::NAME);
        $this->setLastRegion($region, isset($regions[$region]) ? 'theme' : 'layout');

        $form = $this->createRegionForm($node, $layoutModel, $layout, $theme, $region);

        $availableWidgets = $widgetModel->getWidgets();
        $inheritedWidgets = $node->getInheritedWidgets($region);

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
            'node' => $node->getId(),
            'region' => $region,
        ));

        $this->setTemplateView('cms/backend/node.layout', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
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
     */
    public function widgetAddAction($locale, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, WidgetActionManager $widgetActionManager, NodeModel $nodeModel, $site, $node, $region, $widget) {
        if (!$this->resolveNode($nodeModel, $site, $node) || !$this->resolveRegion($themeModel, $layoutModel, $node, $locale, $region)) {
            return;
        }

        $widgetId = $site->createWidget($widget);
        $node->addWidget($region, $widgetId);

        $nodeModel->setNode($site, 'Created new widget in ' . $site->getName());
        $nodeModel->setNode($node, 'Added widget to ' . $node->getName());

        $widget = clone $widgetModel->getWidget($widget);
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
     */
    public function widgetDeleteAction($locale, ThemeModel $themeModel, LayoutModel $layoutModel, NodeModel $nodeModel, $site, $node, $region, $widget) {
        if (!$this->resolveNode($nodeModel, $site, $node) || !$this->resolveRegion($themeModel, $layoutModel, $node, $locale, $region)) {
            return;
        }

        $node->deleteWidget($region, $widget);

        $nodeModel->setNode($node, 'Deleted widget from ' . $node->getName());
    }

    /**
     * Action to reorder the widgets of the provided region
     */
    public function orderAction($locale, ThemeModel $themeModel, LayoutModel $layoutModel, NodeModel $nodeModel, $site, $node, $region) {
        if (!$this->resolveNode($nodeModel, $site, $node) || !$this->resolveRegion($themeModel, $layoutModel, $node, $locale, $region)) {
            return;
        }

        $widgetsValue = $this->request->getQueryParameter('widgets');
        $widgetsValue = rtrim(trim($widgetsValue),',');

        $node->orderWidgets($region, $widgetsValue);

        $nodeModel->setNode($node, 'Reordered widgets on ' . $node->getName());
    }

    /**
     * Creates a form to select the region
     * @param ride\library\cms\node\Node $node
     * @param ride\library\cms\layout\Layout $layout
     * @param ride\library\cms\theme\Theme $theme
     * @param string $region
     * @return ride\library\form\Form
     */
    protected function createRegionForm(Node $node, LayoutModel $layoutModel, Layout $layout = null, Theme $theme = null, $region = null) {
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
        $form->setRequest($this->request);

        return $form->build();
    }

    /**
     * Gets the last region type which has been executed.
     *
     * This is used to if a user changes node in the UI, he will stay in the
     * same region if available
     * @param boolean isLayoutAvailable Flag to see if the layout is available
     * @return string|null Name of the region if a node has been opened, null
     * otherwise
     */
    protected function getLastRegion($isLayoutAvailable) {
        if (!$this->request->hasSession()) {
            return null;
        }

        $session = $this->request->getSession();
        $region = null;

        if ($isLayoutAvailable && $session->get(self::SESSION_LAST_REGION . 'type') != 'theme') {
            $region = $session->get(self::SESSION_LAST_REGION . 'layout');
        }

        if (!$region) {
            $region = $session->get(self::SESSION_LAST_REGION . 'theme');
        }

        return $region;
    }

    /**
     * Sets the last region type to the session
     * @param string $region Name of the last region
     * @return null
     */
    protected function setLastRegion($region, $type) {
        $session = $this->request->getSession();
        $session->set(self::SESSION_LAST_REGION . $type, $region);
        $session->set(self::SESSION_LAST_REGION . 'type', $type);
    }

}
