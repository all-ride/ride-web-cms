<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\node\type\ReferenceNodeType;
use ride\library\cms\node\Node;

use ride\web\base\controller\AbstractController;

/**
 * Abstract controller of a node action
 */
abstract class AbstractNodeAction extends AbstractController implements NodeAction {

    /**
     * Get the machine name of this action
     * @return string
     */
    public function getName() {
        return static::NAME;
    }

    /**
     * Get the route of this action
     * @return string
     */
    public function getRoute() {
        return static::ROUTE;
    }

    /**
     * Checks if this action is available for the node
     * @param \ride\library\cms\node\Node $node
     * @return boolean true if available
     */
    public function isAvailableForNode(Node $node) {
        return $node->getType() !== ReferenceNodeType::NAME;
    }

}
