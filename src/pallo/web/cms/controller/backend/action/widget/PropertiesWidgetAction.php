<?php

namespace pallo\web\cms\controller\backend\action\widget;

use pallo\library\cms\layout\LayoutModel;
use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\cms\widget\Widget;
use pallo\library\cms\widget\WidgetModel;
use pallo\library\i18n\I18n;
use pallo\library\reflection\Invoker;

use pallo\web\mvc\controller\AbstractController;
use pallo\web\mvc\view\TemplateView;

/**
 * Controller of the layout node action
 */
class PropertiesWidgetAction extends AbstractWidgetAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'properties';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.widget.properties';

    /**
     * Checks if this action is available for the widget
     * @param pallo\library\cms\node\Node $node
     * @param pallo\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget) {
        return $widget->getPropertiesCallback() ? true : false;
    }

    /**
     * Action to dispatch to the properties of a widget
     */
    public function indexAction(I18n $i18n, $locale, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, NodeModel $nodeModel, $site, $node, $region, $widget, Invoker $invoker) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $layout = null;
        if (method_exists($node, 'getLayout') && $layout = $node->getLayout($locale)) {
            $layout = $layoutModel->getLayout($layout);
        }

        $theme = $node->getTheme();
        if ($theme) {
            $theme = $themeModel->getTheme($theme);
        }

        $isThemeRegion = $theme->hasRegion($region);
        $isLayoutRegion = $layout && $layout->hasRegion($region);
        if (!$isThemeRegion && !$isLayoutRegion) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        $widgetId = $widget;

        $widget = $site->getWidget($widgetId);
        $widget = clone $widgetModel->getWidget($widget);
        $widget->setRequest($this->request);
        $widget->setResponse($this->response);
        $widget->setProperties($node->getWidgetProperties($widgetId));
        $widget->setLocale($locale);
        $widget->setRegion($region);

        if ($widget instanceof AbstractController) {
            $widget->setConfig($this->config);
            $widget->setDependencyInjector($this->dependencyInjector);
        }

        $propertiesCallback = $widget->getPropertiesCallback();
        if (!$propertiesCallback) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);

            return;
        }

        if ($invoker->invoke($propertiesCallback)) {
            $nodeModel->setNode($node);

            $this->response->setRedirect($this->getUrl('cms.node.layout.region', array(
                'locale' => $locale,
            	'site' => $site->getId(),
            	'node' => $node->getId(),
            	'region' => $region,
            )));

            return;
        }

        $view = $this->response->getView();
        if (!$view instanceof TemplateView) {
            return;
        }

        $inheritedWidgets = $node->getInheritedWidgets($region);
        if (isset($inheritedWidgets[$widgetId])) {
            $this->addWarning('warning.widget.properties.inherited');
        }

        $template = $view->getTemplate();
        $variables = array(
            'site' => $site,
            'node' => $node,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
            'region' => $region,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'propertiesTemplate' => $template->getResource(),
        ) + $template->getVariables();

        $this->setTemplateView('cms/backend/widget.properties', $variables);
    }

}