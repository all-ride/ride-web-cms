<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\node\type\NodeTypeManager;
use pallo\library\cms\node\Node;
use pallo\library\cms\node\NodeModel;

/**
 * Controller of the go node action
 */
class GoNodeAction extends AbstractNodeAction {

    /**
     * The name of this action
     * @var string
     */
    const NAME = 'go';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.go';

    /**
     * Instance of the node type manager
     * @var pallo\library\cms\node\type\NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * Constructs a new go action
     * @param pallo\library\cms\node\type\NodeTypeManager $nodeTypeManager
     */
    public function __construct(NodeTypeManager $nodeTypeManager) {
        $this->nodeTypeManager = $nodeTypeManager;
    }

    /**
     * Checks if this action is available for the node
     * @param pallo\library\cms\node\Node $node
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
     * Perform the go node action
     * @return null
     */
    public function indexAction($locale, NodeModel $nodeModel, $site, $node) {
        if (!$this->resolveNode($nodeModel, $site, $node)) {
            return;
        }

        $route = $node->getRoute($locale);

        $baseUrl = $node->getRootNode()->getBaseUrl($locale);
        if (!$baseUrl) {
            $baseUrl = $this->request->getBaseScript();
        }

        $this->response->setRedirect($baseUrl . $route);
    }

}