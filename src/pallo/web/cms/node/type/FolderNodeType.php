<?php

namespace pallo\web\cms\node\type;

use pallo\library\cms\node\type\FolderNodeType as LibraryFolderNodeType;

/**
 * Interface for a node type
 */
class FolderNodeType extends LibraryFolderNodeType implements NodeType {

    /**
     * Gets the id of the route to create a new node of this type
     * @return string Route id
     */
    public function getRouteAdd() {
        return 'cms.folder.add';
    }

    /**
     * Gets the id of the route to edit a node of this type
     * @return string Route id
     */
    public function getRouteEdit() {
        return 'cms.folder.edit';
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