<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
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
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->hasParent();
    }

    /**
     * Perform the analytics node action
     */
    public function indexAction(Cms $cms, $locale, $site, $node, $revision) {
        $node = $site;
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');

        $data = array(
            'gtm_id' => $site->getLocalized($locale, 'analytics.gtm_id'),
            'ga_id' => $site->getLocalized($locale, 'analytics.ga_id')
        );

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

                foreach ($data as $tag => $id) {
                    $site->setLocalized($locale, 'analytics.' . $tag, $id ? $id : null);
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
