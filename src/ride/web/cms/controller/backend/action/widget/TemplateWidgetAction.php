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

use ride\web\cms\form\TemplatesComponent;
use ride\web\mvc\controller\AbstractController;

/**
 * Controller of the templates widget action
 */
class TemplateWidgetAction extends AbstractWidgetAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'templates';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.widget.templates';

    /**
     * Checks if this action is available for the widget
     * @param ride\library\cms\node\Node $node
     * @param ride\library\cms\widget\Widget $widget
     * @return boolean true if available
     */
    public function isAvailableForWidget(Node $node, Widget $widget) {
        return $widget->getTemplates() ? true : false;
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
    public function indexAction(I18n $i18n, $locale, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, NodeModel $nodeModel, $site, $node, $region, $widget, FileBrowser $fileBrowser, TemplateFacade $templateFacade) {
        if (!$this->resolveNode($nodeModel, $site, $node) || !$this->resolveRegion($themeModel, $layoutModel, $node, $locale, $region)) {
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

        $templates = $widget->getTemplates();
        foreach ($templates as $index => $template) {
            $tokens = explode('/', $template);
            $name = array_pop($tokens);

            unset($templates[$index]);
            $templates[$name] = $template;
        }

        $component = new TemplatesComponent();
        $data = $component->createData($templateFacade, $templates, $node->getTheme(), $widgetId);

        $form = $this->buildForm($component, $data);
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $applicationDirectory = $fileBrowser->getApplicationDirectory();
                foreach ($templates as $name => $template) {
                    $file = $applicationDirectory->getChild($data['path'][$name]);
                    $file->getParent()->create();
                    $file->write($data['content'][$name]);
                }

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

        $this->setTemplateView('cms/backend/widget.templates', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
            'region' => $region,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'widgetName' => $this->getTranslator()->translate('widget.' . $widget->getName()),
            'templates' => $templates,
            'form' => $form->getView(),
        ));
    }

}
