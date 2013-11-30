<?php

namespace pallo\web\cms\controller\backend\action\node;

use pallo\library\cms\exception\CmsException;

/**
 * Manager of the node actions
 */
class NodeActionManager {

    /**
     * Array with NodeAction objects as value and their name as key
     * @var array
     */
    protected $nodeActions;

    /**
     * Construct this manager
     * @return null
     */
    public function __construct() {
        $this->nodeActions = array();
    }

    /**
     * Gets the available node actions
     * @return array Array with the name of the action as key and the
     * implementation as value
     */
    public function getNodeActions() {
        return $this->nodeActions;
    }

    /**
     * Gets the implementation of a node action
     * @param string $name
     * @return NodeAction
     */
    public function getNodeAction($name) {
        if (!$this->hasNodeAction($name)) {
            throw new CmsException('Could not get node action ' . $name . ': action is not registered');
        }

        return $this->nodeActions[$name];
    }

    /**
     * Checks whether a node action is available in this manager
     * @param string $name Name of the node action
     * @return boolean True if the node action is added, false otherwise
     */
    public function hasNodeAction($name) {
        return isset($this->nodeActions[$name]);
    }

    /**
     * Adds a node action to this manager
     * @param NodeAction $nodeAction
     * @return null
     */
    public function addNodeAction(NodeAction $nodeAction) {
        $this->nodeActions[$nodeAction->getName()] = $nodeAction;
    }

    /**
     * Removes a node action from this manager
     * @param string $name Name of the node action
     * @return null
     */
    public function removeNodeAction($name) {
        if ($this->hasNodeAction($name)) {
            unset($this->nodeActions[$name]);
        }
    }

}