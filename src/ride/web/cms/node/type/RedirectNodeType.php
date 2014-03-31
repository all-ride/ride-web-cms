<?php

namespace ride\web\cms\node\type;

use ride\library\cms\node\type\RedirectNodeType as LibraryRedirectNodeType;

/**
 * Interface for a node type
 */
class RedirectNodeType extends LibraryRedirectNodeType implements NodeType {

    /**
     * Gets the callback for the frontend route
     * @return string|array| \ride\web\cms\controller\frontend\RedirectController
     */
    public function getFrontendCallback() {
        return array('ride\\web\\cms\\controller\\frontend\\RedirectController', 'indexAction');
    }

    /**
     * Gets the id of the route to create a new node of this type
     * @return string Route id
     */
    public function getRouteAdd() {
        return 'cms.redirect.add';
    }

    /**
     * Gets the id of the route to edit a node of this type
     * @return string Route id
     */
    public function getRouteEdit() {
        return 'cms.redirect.edit';
    }

    /**
     * Gets the id of the route to clone a node of this type
     * @return string Route id
     */
    public function getRouteClone() {
        return 'cms.node.clone';
    }

    /**
     * Gets the id of the route to delete a node of this type
     * @return string Route id
     */
    public function getRouteDelete() {
        return 'cms.node.delete';
    }

}