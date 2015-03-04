<?php

namespace ride\web\cms\node\tree;

use ride\library\cms\node\Node;

/**
 * Interface to generate a node tree
 */
interface NodeTreeGenerator {

    /**
     * Gets a site tree for the provided node
     * @param \ride\library\cms\node\Node $node Selected node of the tree
     * @param string $locale Locale for the tree
     * @return TreeNode Tree Node for the site of the provided node
     */
    public function getTree(Node $node, $locale);

}
