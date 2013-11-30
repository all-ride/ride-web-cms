<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\node\Node;
use pallo\library\mvc\controller\Controller;

/**
 * Interface of a node action
 */
interface NodeAction extends Controller {

    /**
     * Get the machine name of this action
     * @return string
     */
    public function getName();

    /**
     * Get the route of this action
     * @return string
     */
    public function getRoute();

    /**
     * Checks if this action is available for the node
     * @param pallo\library\cms\node\Node $node
     * @return boolean True if available
     */
    public function isAvailableForNode(Node $node);

}