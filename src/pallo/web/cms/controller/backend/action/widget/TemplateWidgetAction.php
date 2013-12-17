<?php

namespace pallo\web\cms\controller\backend\action\widget;

use pallo\library\cms\layout\LayoutModel;
use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\cms\widget\Widget;
use pallo\library\cms\widget\WidgetModel;
use pallo\library\i18n\I18n;
use pallo\library\system\file\browser\FileBrowser;
use pallo\library\template\TemplateFacade;
use pallo\library\validation\exception\ValidationException;

use pallo\web\cms\form\TemplatesComponent;

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
     * @param pallo\library\cms\node\Node $node
     * @param pallo\library\cms\widget\Widget $widget
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
        $widget = $widgetModel->getWidget($widget);

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
            } catch (ValidationException $saveException) {
                $form->setValidationException($validationException);
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