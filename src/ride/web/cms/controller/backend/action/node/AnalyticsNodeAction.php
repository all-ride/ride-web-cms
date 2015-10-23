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
    public function indexAction(Cms $cms, $locale, $site, $node, $revision) {
        $node = $site;
        if (!$cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $data = array(
            'gtm_id' => $site->get('analytics.gtm_id'),
            'ga_id' => $site->get('analytics.ga_id')
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('gtm_id', 'string', array(
            'label' => $translator->translate('label.analytics.gtm_id'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'regex' => array(
                    'regex' => '/^((GTM-[A-Z0-9]{6}))$/',
                    'error.regex' => 'label.analytics.gtm_id.error',
                    'required' => false,
                ),
            ),
        ));
        $form->addRow('ga_id', 'string', array(
            'label' => $translator->translate('label.analytics.ga_id'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'regex' => array(
                    'regex' => '/^(UA-[0-9]+-[0-9][0-9]??)$/',
                    'error.regex' => 'label.analytics.ga_id.error',
                    'required' => false,
                ),
            ),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                foreach ($data as $tag => $id) {
                    $site->set('analytics.' . $tag, $id);
                }

                $cms->saveNode($site, "Set analytics for " . $site->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale)
                ));

                $url = $this->getUrl(self::ROUTE, array(
                    'site' => $site->getId(),
                    'revision' => $node->getRevision(),
                    'locale' => $locale,
                    'node' => $node->getId(),
                ));
                if ($referer) {
                    $url .= '?referer=' . urlencode($referer);
                }

                $this->response->setRedirect($url);

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $this->setTemplateView('cms/backend/site.analytics', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
