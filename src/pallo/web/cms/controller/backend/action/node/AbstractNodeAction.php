<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\exception\CmsException;
use pallo\library\cms\node\Node;

use pallo\web\cms\controller\backend\AbstractBackendController;

/**
 * Abstract controller of a node action
 */
abstract class AbstractNodeAction extends AbstractBackendController implements NodeAction {

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
     * @param joppa\model\Node $node
     * @return boolean true if available
     */
    public function isAvailableForNode(Node $node) {
        return true;
    }

}