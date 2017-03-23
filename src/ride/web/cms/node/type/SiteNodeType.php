<?php

namespace ride\web\cms\node\type;

use ride\library\cms\node\type\SiteNodeType as LibrarySiteNodeType;

/**
 * Frontend implementation for a site node type
 */
class SiteNodeType extends LibrarySiteNodeType implements NodeType {

    /**
     * Gets the id of the route to create a new node of this type
     * @return string Route id
     */
    public function getRouteAdd() {
        return 'cms.site.add';
    }

    /**
     * Gets the id of the route to edit a node of this type
     * @return string Route id
     */
    public function getRouteEdit() {
        return 'cms.site.edit';
    }

    /**
     * Gets the id of the route to clone a node of this type
     * @return string Route id
     */
    public function getRouteClone() {
        return 'cms.site.clone';
    }

    /**
     * Gets the id of the route to delete a node of this type
     * @return string Route id
     */
    public function getRouteDelete() {
        return 'cms.site.delete';
    }

}
