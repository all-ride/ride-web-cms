<?php

namespace ride\web\cms\controller\backend\action\widget;

use ride\library\cms\node\Node;
use ride\library\cms\widget\Widget;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\controller\widget\StyleWidget;
use ride\web\cms\Cms;

/**
 * Controller of the style widget action
 */
class StyleWidgetAction extends AbstractWidgetAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'style';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.content.widget.style';

    /**
     * Checks if this action is available for the widget
     * @param ride\library\cms\node\Node $node
     * @param ride\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget) {
        return $widget instanceof StyleWidget && $widget->getWidgetStyleOptions() ? true : false;
    }

    /**
     * Action to dispatch to the properties of a widget
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @param string $region
     * @param string $widget
     * @return null
     */
    public function indexAction(Cms $cms, $locale, $site, $revision, $node, $region, $widget) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $widgetId = $widget;

        $widget = $site->getWidget($widgetId);
        if (!$this->getSecurityManager()->isPermissionGranted('cms.widget.' . $widget . '.' . self::NAME)) {
            throw new UnauthorizedException();
        }

        $widgetProperties = $node->getWidgetProperties($widgetId);

        $widget = $site->getWidget($widgetId);
        $widget = clone $cms->getWidget($widget);
        $widget->setRequest($this->request);
        $widget->setResponse($this->response);
        $widget->setProperties($widgetProperties);
        $widget->setLocale($locale);
        $widget->setRegion($region);
        if ($widget instanceof AbstractController) {
            $widget->setConfig($this->config);
            $widget->setDependencyInjector($this->dependencyInjector);
        }

        $styleOptions = $widget->getWidgetStyleOptions();

        $data = array();
        foreach ($styleOptions as $styleOption => $styleTranslationKey) {
            $data[$styleOption] = $widgetProperties->getWidgetProperty('style.' . $styleOption);
        }

        $translator = $this->getTranslator();

        $form = $this->createFormBuilder($data);
        foreach ($styleOptions as $styleOption => $styleTranslationKey) {
            $form->addRow($styleOption, 'string', array(
                'label' => $translator->translate($styleTranslationKey),
            ));
        }

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                foreach ($styleOptions as $styleOption => $styleTranslationKey) {
                    $widgetProperties->setWidgetProperty('style.' . $styleOption, $data[$styleOption]);
                }

                $cms->saveNode($node, 'Updated style for widget ' . $widgetId . ' in ' . $node->getName());

                $this->addSuccess('success.widget.saved', array(
                    'widget' => $this->getTranslator()->translate('widget.' . $widget->getName()),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.node.content.region',
                    array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'revision' => $node->getRevision(),
                        'node' => $node->getId(),
                        'region' => $region,
                    )
                ));

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/widget.style', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'widgetName' => $this->getTranslator()->translate('widget.' . $widget->getName()),
            'form' => $form->getView(),
        ));
    }

}
