<?php

namespace ride\web\cms\node\tree;

use ride\library\cms\node\Node;
use ride\library\cms\node\NodeModel;
use ride\library\i18n\translator\Translator;
use ride\library\security\SecurityManager;

use ride\web\cms\Cms;
use ride\web\WebApplication;

/**
 * Generic implementation to generate a tree for a site
 */
class GenericNodeTreeGenerator implements NodeTreeGenerator {

    /**
     * Constructs a new generic tree generator
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param \ride\web\cms\Cms $cms Instance of the CMS facade
     * @param \ride\library\security\SecurityManager $securityManager Instance
     * of the security manager
     * @param \ride\library\i18n\translator\Translator $translator Instance of
     * the translator
     * @param array $actions Array with the name of the node action as key and
     * the instance as value
     * @return null
     * @see \ride\web\cms\controller\backend\action\node\NodeAction
     */
    public function __construct(WebApplication $web, Cms $cms, SecurityManager $securityManager, Translator $translator, array $actions) {
        $this->cms = $cms;
        $this->securityManager = $securityManager;
        $this->web = $web;
        $this->translator = $translator;
        $this->actions = $actions;
    }

    /**
     * Gets a site tree for the provided node
     * @param \ride\library\cms\node\Node $node Selected node of the tree
     * @param string $locale Locale for the tree
     * @return TreeNode Tree Node for the site of the provided node
     */
    public function getTree(Node $node, $locale) {
        $this->locale = $locale;
        $this->node = $node;
        $this->nodeId = $node->getId();
        $this->collapsedNodes = $this->cms->getCollapsedNodes();
        $this->referer = '?referer=' . urlencode($this->web->getRequest()->getUrl());

        if ($this->nodeId) {
            $this->rootNodeId = $node->getRootNodeId();
        } else {
            $parentNode = $node->getParentNode();
            if ($parentNode) {
                $this->rootNodeId = $parentNode->getRootNodeId();
            } else {
                return;
            }
        }

        $site = $this->cms->getNode($this->rootNodeId, $node->getRevision(), $this->rootNodeId, null, true);
        $onlyCurrentLocale = $site->isLocalizationMethodUnique();

        return $this->getTreeNode($site, $onlyCurrentLocale);
    }

    /**
     * Gets the tree node of a node
     * @param \ride\library\cms\node\Node $node Node to process
     * @param boolean $onlyCurrentLocale Flag to see if the tree should only
     * rendered for the current locale
     * @return TreeNode
     */
    protected function getTreeNode(Node $node, $onlyCurrentLocale) {
        $nodeId = $node->getId();
        $nodeRevision = $node->getRevision();

        $urlVars = array(
            'site' => $this->rootNodeId,
            'revision' => $nodeRevision,
            'node' => $nodeId,
            'locale' => $this->locale,
        );
        $url = $this->web->getUrl('cms.node.default', $urlVars) . $this->referer;

        $treeNode = new TreeNode($node, $this->locale, $url);

        // checks if this node is selected
        if ($this->nodeId == $nodeId) {
            $treeNode->setIsSelected(true);
        }

        if (isset($this->collapsedNodes[$node->getPath() . '[' . $nodeRevision . ']'])) {
            $treeNode->setIsCollapsed(true);
        }

        // add icon state classes
        $nodeType = $this->cms->getNodeType($node);
        if ($nodeType->getFrontendCallback()) {
            if ($node->getRoute($this->locale) == '/') {
                $treeNode->setIsHomePage(true);
            }

            if (!$node->isPublished()) {
                $treeNode->setIsHidden(true);
            }
        }

        $actions = array();
        foreach ($this->actions as $actionName => $action) {
            if (!$action->isAvailableForNode($node)) {
                continue;
            }

            $actionUrl = $this->web->getUrl($action->getRoute(), $urlVars) . $this->referer;
            if (!$this->securityManager->isUrlAllowed($actionUrl)) {
                continue;
            }

            $actions[$actionName] = $actionUrl;
        }

        $actionUrl = $this->web->getUrl($nodeType->getRouteEdit(), $urlVars) . $this->referer;
        if ($this->securityManager->isUrlAllowed($actionUrl)) {
            $actions['edit'] = $actionUrl;
        }

        $actionUrl = $this->web->getUrl($nodeType->getRouteClone(), $urlVars) . $this->referer;
        if ($this->securityManager->isUrlAllowed($actionUrl)) {
            $actions['clone'] = $actionUrl;
        }

        $actionUrl = $this->web->getUrl($nodeType->getRouteDelete(), $urlVars) . $this->referer;
        if ($this->securityManager->isUrlAllowed($actionUrl)) {
            $actions['delete'] = $actionUrl;
        }

        $treeNode->setActions($actions);

        $children = $node->getChildren();
        if ($children) {
            foreach ($children as $childId => $child) {
                if (!$onlyCurrentLocale || ($onlyCurrentLocale && $child->isAvailableInLocale($this->locale))) {
                    $children[$childId] = $this->getTreeNode($child, $onlyCurrentLocale);
                } else {
                    unset($children[$childId]);
                }
            }

            $treeNode->setChildren($children);
        }

        return $treeNode;
    }

}
