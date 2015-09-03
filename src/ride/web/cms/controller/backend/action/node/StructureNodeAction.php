<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\structure\NodeStructureParser;
use ride\library\cms\node\Node;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\Cms;

/**
 * Controller of the site structure node action
 */
class StructureNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
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
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        return !$node->hasParent();
    }

    /**
     * Perform the structure node action
     */
    public function indexAction(Cms $cms, NodeStructureParser $parser, $locale, $site, $revision) {
        $node = $site;
        if (!$cms->resolveNode($site, $revision, $node, null, true)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $translator = $this->getTranslator();
        $referer = $this->request->getQueryParameter('referer');
        $structure = $parser->getStructure($locale, $node);

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

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $parser->setStructure($locale, $node, $cms->getNodeModel(), $data['structure']);

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

        $this->setTemplateView('cms/backend/site.structure', array(
            'site' => $site,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
        ));
    }

}
