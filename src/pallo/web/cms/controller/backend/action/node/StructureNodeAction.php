<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\node\structure\NodeStructureParser;
use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;
use pallo\library\i18n\I18n;
use pallo\library\validation\exception\ValidationException;

/**
 * Controller of the site structure node action
 */
class StructureNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'structure';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.site.structure';

    /**
     * Checks if this action is available for the node
     * @param pallo\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->hasParent();
    }

    /**
     * Perform the structure node action
     */
    public function indexAction(I18n $i18n, $locale, NodeStructureParser $parser, NodeModel $nodeModel, $site) {
        $node = null;
        if (!$this->resolveNode($nodeModel, $site, $node, null, true)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $structure = $parser->getStructure($locale, $site);
        $translator = $this->getTranslator();

        $form = $this->createFormBuilder(array('structure' => $structure));
        $form->addRow('structure', 'text', array(
            'label' => $translator->translate('label.node.structure'),
            'description' => $translator->translate('label.node.structure.description'),
            'attributes' => array(
        	   'rows' => 10,
            ),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            )
        ));
        $form->setRequest($this->request);

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $parser->setStructure($locale, $site, $nodeModel, $data['structure']);

                $this->addSuccess('success.node.saved', array(
                    'node' => $site->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $validationException) {
            	$form->setValidationException($validationException);
            }
        }

        $this->setTemplateView('cms/backend/site.structure', array(
            'site' => $site,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

}