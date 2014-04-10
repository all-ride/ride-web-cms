<?php

namespace ride\web\cms\controller\backend;

use ride\web\base\controller\AbstractController;

/**
 * Controller to handle extra actions on the node tree
 */
class TreeController extends AbstractController {

    /**
     * Name of the session variable which holds the nodes which are collapsed
     * @var string
     */
    const SESSION_TOGGLED_NODES = 'cms.tree.toggle';

    /**
     * Action to toggle the collapse state of the provided node
     * @param string $node Id of the node
     * @return null
     */
    public function toggleNodeAction($node) {
        $session = $this->request->getSession();
        $securityManager = $this->getSecurityManager();
        $user = $securityManager->getUser();

        if ($user) {
            $nodes = $user->getPreference(self::SESSION_TOGGLED_NODES, array());
        } else {
            $nodes = array();
        }

        $nodes = $session->get(self::SESSION_TOGGLED_NODES, $nodes);
        if (isset($nodes[$node])) {
            unset($nodes[$node]);
        } else {
            $nodes[$node] = true;
        }

        $session->set(self::SESSION_TOGGLED_NODES, $nodes);

        if ($user) {
            $user->setPreference(self::SESSION_TOGGLED_NODES, $nodes);

            $securityManager->getSecurityModel()->saveUser($user);
        }
    }

}
