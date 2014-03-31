<?php

namespace ride\web\cms\controller\backend\action\widget;

use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\cms\theme\ThemeModel;
use ride\library\cms\widget\Widget;
use ride\library\cms\widget\WidgetModel;
use ride\library\i18n\I18n;
use ride\library\system\file\browser\FileBrowser;
use ride\library\template\TemplateFacade;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\controller\widget\StyleWidget;

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
    const ROUTE = 'cms.widget.style';

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
     * @param I18n $i18n
     * @param string $locale
     * @param ThemeModel $themeModel
     * @param LayoutModel $layoutModel
     * @param WidgetModel $widgetModel
     * @param NodeModel $nodeModel
     * @param string $site
     * @param string $node
     * @param string $region
     * @param string $widget
     * @param FileBrowser $fileBrowser
     * @param TemplateFacade $templateFacade
     * @return null
     */
    public function indexAction(I18n $i18n, $locale, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, NodeModel $nodeModel, $site, $node, $region, $widget) {
        if (!$this->resolveNode($nodeModel, $site, $node) || !$this->resolveRegion($themeModel, $layoutModel, $node, $locale, $region)) {
            return;
        }

        $widgetId = $widget;
        $widgetProperties = $node->getWidgetProperties($widgetId);

        $widget = $site->getWidget($widgetId);
        $widget = clone $widgetModel->getWidget($widget);
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
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                foreach ($styleOptions as $styleOption => $styleTranslationKey) {
                    $widgetProperties->setWidgetProperty('style.' . $styleOption, $data[$styleOption]);
                }

                $nodeModel->setNode($node, 'Updated style for widget ' . $widgetId . ' in ' . $node->getName());

                $this->addSuccess('success.widget.saved', array(
                    'widget' => $this->getTranslator()->translate('widget.' . $widget->getName()),
                ));

                $this->response->setRedirect($this->getUrl(
                    'cms.node.layout',
                    array(
                        'locale' => $locale,
                        'site' => $site->getId(),
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
            'locales' => $i18n->getLocaleCodeList(),
            'region' => $region,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'widgetName' => $this->getTranslator()->translate('widget.' . $widget->getName()),
            'form' => $form->getView(),
        ));
    }

}
