<?php

namespace ride\web\cms\controller\backend\action\widget;

use ride\library\cms\node\Node;
use ride\library\cms\widget\NodeWidgetProperties;
use ride\library\cms\widget\Widget;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

/**
 * Controller of the cache widget action
 */
class CacheWidgetAction extends AbstractWidgetAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'cache';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.content.widget.cache';

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
        $widgetProperties = $node->getWidgetProperties($widgetId);

        $widget = $site->getWidget($widgetId);
        $widget = clone $cms->getWidget($widget);

        $translator = $this->getTranslator();

        $data = array(
            'cache' => $widgetProperties->getCache(),
            'cache-ttl' => $widgetProperties->getCacheTtl(),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('cache', 'option', array(
            'label' => $translator->translate('label.cache'),
            'options' => array(
                NodeWidgetProperties::CACHE_AUTO => $translator->translate('label.automatic') . ' (' . strtolower($translator->translate('label.' . ($widget->isAutoCache() ? 'enabled' : 'disabled'))) . ')',
                NodeWidgetProperties::CACHE_ENABLED => $translator->translate('label.enabled'),
                NodeWidgetProperties::CACHE_DISABLED => $translator->translate('label.disabled'),
            ),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('cache-ttl', 'number', array(
            'label' => $translator->translate('label.cache.ttl'),
            'description' => $translator->translate('label.cache.ttl.description'),
            'validators' => array(
                'minmax' => array(
                    'minimum' => 0,
                    'required' => true,
                ),
            ),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $widgetProperties->setCache($data['cache']);
                $widgetProperties->setCacheTtl($data['cache-ttl']);

                $cms->saveNode($node, 'Updated cache for widget ' . $widgetId . ' in ' . $node->getName());

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

        $this->setTemplateView('cms/backend/widget.cache', array(
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
