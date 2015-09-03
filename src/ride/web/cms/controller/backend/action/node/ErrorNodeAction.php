<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

/**
 * Controller of the error node action
 */
class ErrorNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'error';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.site.error';

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->hasParent();
    }

    /**
     * Perform the error node action
     */
    public function indexAction(Cms $cms, $locale, $site, $revision) {
        $node = $site;
        if (!$cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $cms->setLastAction(self::NAME);
        $this->setContentLocale($locale);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');
        $nodeList = $cms->getNodeList($node, $locale);

        $data = array(
            'node404' => $site->get('error.404'),
            'node403' => $site->get('error.403'),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('node404', 'select', array(
            'label' => $translator->translate('label.node.404'),
            'options' => $nodeList,
        ));
        $form->addRow('node403', 'select', array(
            'label' => $translator->translate('label.node.403'),
            'options' => $nodeList,
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                foreach ($data as $statusCode => $errorNode) {
                    $statusCode = str_replace('node', '', $statusCode);

                    $site->set('error.' . $statusCode, $errorNode);
                }

                $cms->saveNode($site, "Set error pages for " . $site->getName());

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

        $this->setTemplateView('cms/backend/site.error', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
