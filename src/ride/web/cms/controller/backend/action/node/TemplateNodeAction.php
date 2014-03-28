<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\type\NodeTypeManager;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\i18n\I18n;
use ride\library\system\file\browser\FileBrowser;
use ride\library\template\TemplateFacade;
use ride\library\validation\exception\ValidationException;

use ride\web\cms\form\TemplatesComponent;

/**
 * Controller of the advanced node action
 */
class TemplateNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'templates';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.templates';

    /**
     * Instance of the node type manager
     * @var \ride\library\cms\node\type\NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * Constructs a new go action
     * @param \ride\library\cms\node\type\NodeTypeManager $nodeTypeManager
     */
    public function __construct(NodeTypeManager $nodeTypeManager) {
        $this->nodeTypeManager = $nodeTypeManager;
    }

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node) {
        if (!$node->getParent()) {
            return true;
        }

        $nodeType = $this->nodeTypeManager->getNodeType($node->getType());

        return $nodeType->getFrontendCallback() ? true : false;
    }

    /**
     * Perform the template node action
     */
    public function indexAction(TemplateFacade $templateFacade, FileBrowser $fileBrowser, I18n $i18n, $locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $this->setLastAction(self::NAME);

        $templates = array(
        	'index' => 'cms/frontend/index',
        );

        if ($node->getType() == 'page') {
            $layout = $node->getLayout($locale);

            $templates[$layout] = 'cms/frontend/layout.' . $layout;
        }

        if ($node->getId() == $site->getId()) {
            $id = null;
        } else {
            $id = $node->getId();
        }

        $component = new TemplatesComponent();
        $data = $component->createData($templateFacade, $templates, $node->getTheme(), $id);

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

                $this->addSuccess('success.node.saved', array(
                    'node' => $node->getName($locale)
                ));

                $this->response->setRedirect($this->request->getUrl());

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/node.templates', array(
            'site' => $site,
            'node' => $node,
            'templates' => $templates,
            'form' => $form->getView(),
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $i18n->getLocaleCodeList(),
        ));
    }

}
