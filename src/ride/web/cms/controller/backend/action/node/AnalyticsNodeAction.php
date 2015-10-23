<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

/**
 * Controller of the analytics node action
 */
class AnalyticsNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'analytics';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.site.analytics';

    /**
     * Perform the meta node action
     */
    public function indexAction(Cms $cms,  $locale, $site, $revision, $node) {

        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $data = array(
            'analytics' => array(),
        );
        
        $meta = $node->getMeta($locale, null, false);
        foreach ($meta as $property => $content) {
            switch ($property) {
                case 'gtm_id':
                    $data['gtm_id'] = $content;

                    break;
                case 'ga_id':
                    $data['ga_id'] = $content;

                    break;
                default:
                    $data['analytics'][] = $property . '=' . $content;

                    break;
            }
        }

        $form = $this->createFormBuilder($data);
        $form->addRow('gtm_id', 'string', array(
            'label' => $translator->translate('label.analytics.gtm_id'),
            'filters' => array(
                'trim' => array(),
            ),
        ));
        $form->addRow('ga_id', 'string', array(
            'label' => $translator->translate('label.analytics.ga_id'),
            'filters' => array(
                'trim' => array(),
            ),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $analytics = array();

                if ($data['gtm_id']) {
                    $analytics['gtm_id'] = $data['gtm_id'];
                }
                if ($data['ga_id']) {
                    $analytics['ga_id'] = $data['ga_id'];
                }
                foreach ($data['analytics'] as $property) {
                    list($property, $content) = explode('=', $property, 2);
                    $analytics[$property] = $content;
                }
                foreach ($analytics as $tag => $id) {

                    $site->set('analytics.' . $tag, $id);
                }

//                $cms->saveNode($node, 'Set meta tags to ' . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $url = $this->getUrl(self::ROUTE, array(
                    'site' => $site->getId(),
                    'revision' => $node->getRevision(),
                    'locale' => $locale,
                    'node' => $node->getId(),
                ));
                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $this->setTemplateView('cms/backend/node.analytics', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales()
        ));
    }

}
