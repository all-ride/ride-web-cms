<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

/**
 * Controller of the publish node action
 */
class PublishNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'publish';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.publish';

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->getRootNode()->isAutoPublish();
    }

    /**
     * Perform the publish node action
     */
    public function indexAction(Cms $cms, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $defaultRevision = $cms->getDefaultRevision();

        $data = array(
            'revision' => $defaultRevision,
            'recursive' => true,
        );

        $revisions = $site->getRevisions();
        if (!isset($revisions[$defaultRevision])) {
            $revisions[$defaultRevision] = $defaultRevision;
        }
        unset($revisions[$node->getRevision()]);

        $form = $this->createFormBuilder($data);
        $form->addRow('revision', 'select', array(
            'label' => $translator->translate('label.revision'),
            'description' => $translator->translate('label.revision.publish.description'),
            'options' => $revisions,
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('recursive', 'option', array(
            'label' => $translator->translate('label.recursive'),
            'description' => $translator->translate('label.recursive.publish.description'),
        ));

        $referer = $this->request->getQueryParameter('referer');

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $cms->publishNode($node, $data['revision'], $data['recursive']);

                $this->addSuccess('success.node.published', array(
                    'node' => $node->getName($locale),
                    'revision' => $data['revision'],
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

        $this->setTemplateView('cms/backend/node.publish', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
