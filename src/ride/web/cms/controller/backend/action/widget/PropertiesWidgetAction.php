<?php

namespace ride\web\cms\controller\backend\action\widget;

use ride\library\cms\node\Node;
use ride\library\cms\widget\Widget;
use ride\library\http\Response;
use ride\library\reflection\Invoker;

use ride\web\cms\Cms;
use ride\web\mvc\controller\AbstractController;
use ride\web\mvc\view\TemplateView;

/**
 * Controller of the properties widget action
 */
class PropertiesWidgetAction extends AbstractWidgetAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'properties';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.content.widget.properties';

    /**
     * Checks if this action is available for the widget
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget) {
        return $widget->getPropertiesCallback() ? true : false;
    }

    /**
     * Action to dispatch to the properties of a widget
     */
    public function indexAction(Cms $cms, $locale, $site, $revision, $node, $region, $section, $block, $widget, Invoker $invoker) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $widgetId = $widget;

        $widget = $site->getWidget($widgetId);
        $widget = clone $cms->getWidget($widget);
        $widget->setRequest($this->request);
        $widget->setResponse($this->response);
        $widget->setProperties($node->getWidgetProperties($widgetId));
        $widget->setLocale($locale);
        $widget->setRegion($region);
        $widget->setSection($section);
        $widget->setBlock($block);
        $widget->setIdentifier($widgetId);

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
            $cms->saveNode($node, 'Updated properties for widget ' . $widgetId . ' in ' . $node->getName());

            $this->addSuccess('success.widget.saved', array(
            	'widget' => $this->getTranslator()->translate('widget.' . $widget->getName()),
            ));
        }

        $widgetView = $this->response->getView();
        if (!$widgetView && !$this->response->getBody() && $this->response->getStatusCode() == Response::STATUS_CODE_OK) {
            $this->response->setRedirect($this->getUrl('cms.node.content.region', array(
                'locale' => $locale,
                'site' => $site->getId(),
            	'revision' => $node->getRevision(),
            	'node' => $node->getId(),
            	'region' => $region,
            )));

            return;
        }

        if (!$widgetView instanceof TemplateView) {
            return;
        }

        $inheritedWidgets = $node->getInheritedWidgets($region, $section);
        if (isset($inheritedWidgets[$block][$widgetId])) {
            $this->addWarning('warning.widget.properties.inherited');
        }

        $referer = $this->request->getQueryParameter('referer');

        $template = $widgetView->getTemplate();
        $variables = array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'section' => $section,
            'block' => $block,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'widgetName' => $this->getTranslator()->translate('widget.' . $widget->getName()),
            'propertiesTemplate' => $template->getResource(),
        ) + $template->getVariables();

        $view = $this->setTemplateView('cms/backend/widget.properties', $variables);
        $view->getTemplate()->setResourceId(substr(md5($template->getResource()), 0, 7));
        $view->addJavascript('js/form.js');
        $view->mergeResources($widgetView);
    }

}
