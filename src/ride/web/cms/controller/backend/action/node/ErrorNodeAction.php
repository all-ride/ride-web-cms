<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\i18n\I18n;
use ride\library\validation\exception\ValidationException;

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
     * @param ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->hasParent();
    }

    /**
     * Perform the error node action
     */
    public function indexAction(I18n $i18n, $locale, NodeModel $nodeModel, $site) {
        $node = null;
        if (!$this->resolveNode($nodeModel, $site, $node, null, true)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $translator = $this->getTranslator();

        $nodeList = array('' => '---') + $nodeModel->getListFromNodes(array($site), $locale);

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
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                foreach ($data as $statusCode => $node) {
                    $statusCode = str_replace('node', '', $statusCode);

                    $site->set('error.' . $statusCode, $node);
                }

                $nodeModel->setNode($site, "Set error pages for " . $node->getName());

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/site.error', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

}
