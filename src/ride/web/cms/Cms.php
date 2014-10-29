<?php

namespace ride\web\cms;

use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\Node;
use ride\library\cms\theme\ThemeModel;
use ride\library\cms\widget\WidgetModel;
use ride\library\cms\Cms as LibraryCms;
use ride\library\i18n\I18n;
use ride\library\mvc\Request;
use ride\library\mvc\Response;
use ride\library\security\SecurityManager;

/**
 * Facade for the CMS
 */
class Cms extends LibraryCms {

    /**
     * Name of the session variable which holds the nodes which are collapsed
     * @var string
     */
    const SESSION_COLLAPSED_NODES = 'cms.nodes.collapsed';

    /**
     * Session key for the last action type
     * @var string
     */
    const SESSION_LAST_ACTION = 'cms.action.last';

    /**
     * Session key for the last region
     * @var string
     */
    const SESSION_LAST_REGION = 'cms.region.last.';

    /**
     * Constructs a new CMS facade
     * @param \ride\library\http\Request $request
     * @param \ride\library\http\Request $response
     * @param \ride\library\i18n\I18n $i18n
     * @param \ride\library\cms\node\NodeModel $nodeModel
     * @param \ride\library\cms\theme\ThemeModel $themeModel
     * @param \ride\library\cms\layout\LayoutModel $layoutModel
     * @param \ride\library\cms\widget\WidgetModel $widgetModel
     * @param \ride\library\security\SecurityManager $securityManager
     * @return null
     */
    public function __construct(Request $request, Response $response, I18n $i18n, NodeModel $nodeModel, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, SecurityManager $securityManager) {
        parent::__construct($nodeModel, $themeModel, $layoutModel, $widgetModel, $securityManager);

        $this->request = $request;
        $this->response = $response;
        $this->i18n = $i18n;
    }

    /**
     * Gets a list of the locales
     * @return array Array with the code of the locale as key and value
     */
    public function getLocales() {
        return $this->i18n->getLocaleCodeList();
    }

    /**
     * Resolves the provided site and node
     * @param string $site Id of the site, will become the site Node instance
     * @param string $revision Name of the revision
     * @param string $node Id of the node, if set will become the Node instance
     * @param string $type Expected node type
     * @param boolean|integer $children Flag to see if child nodes should be
     * fetched
     * @return boolean True when the node is succesfully resolved, false if
     * the node could not be found, the response code will be set to 404
     */
    public function resolveNode(&$site, $revision = null, &$node = null, $type = null, $children = false) {
        $result = parent::resolveNode($site, $revision, $node, $type, $children);

        if (!$result) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
        }

        return $result;
    }

    /**
     * Resolves the provided region
     * @param \ride\library\cms\node\Node $node
     * @param string $locale Code of the locale
     * @param string $region Machine name of the region
     * @param string $theme Machine name of the theme
     * @param string $layout Machine name of the layout
     * @return boolean True when the region is available in the provided node,
     * false if the region is not available, the response code will be set to
     * 404
     */
    public function resolveRegion(Node $node, $locale, $region, &$theme = null, &$layout = null) {
        $result = parent::resolveRegion($node, $locale, $region, $theme, $layout);

        if (!$result) {
            $this->response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
        }

        return $result;
    }

    /**
     * Set or toggle the collapse status of a node
     * @param \ride\library\cms\node\Node $node
     * @param boolean $isCollapsed
     * @return boolean Collapse state of the provided node
     */
    public function collapseNode(Node $node, $isCollapsed = null) {
        $collapsedNodes = $this->getCollapsedNodes();

        $path = $node->getPath() . '[' . $node->getRevision() . ']';

        if ($isCollapsed !== null) {
            if ($isCollapsed && !isset($collapsedNodes[$path])) {
                $collapsedNodes[$path] = true;
            } elseif (!$isCollapsed && isset($collapsedNodes[$path])) {
                unset($collapsedNodes[$path]);
            }
        } elseif (isset($collapsedNodes[$path])) {
            unset($collapsedNodes[$path]);

            $isCollapsed = false;
        } else {
            $collapsedNodes[$path] = true;

            $isCollapsed = true;
        }

        $this->request->getSession()->set(self::SESSION_COLLAPSED_NODES, $collapsedNodes);

        if ($this->user) {
            $this->user->setPreference(self::SESSION_COLLAPSED_NODES, $collapsedNodes);

            $this->securityManager->getSecurityModel()->saveUser($this->user);
        }

        return $isCollapsed;
    }

    /**
     * Gets the collapsed nodes
     * @return array Array with the %path%[%revision%] of the node as key, and
     * a boolean as value
     */
    public function getCollapsedNodes() {
        if ($this->user) {
            $collapsedNodes = $this->user->getPreference(self::SESSION_COLLAPSED_NODES, array());
        } else {
            $collapsedNodes = array();
        }

        if ($this->request->hasSession()) {
            $collapsedNodes = $this->request->getSession()->get(self::SESSION_COLLAPSED_NODES, $collapsedNodes);
        }

        return $collapsedNodes;
    }

    /**
     * Gets the last action type which has been executed.
     *
     * This is used to if a user changes node in the UI, he will stay in the
     * same type of backend page. For example, when the user is in the advanced
     * page, he will come in the advanced page of the other node
     * @param string $default Default action to return when the last action is
     * not set
     * @return string|null Action type if a node has been opened, null
     * otherwise
     */
    public function getLastAction($default = null) {
        if (!$this->request->hasSession()) {
            return $default;
        }

        return $this->request->getSession()->get(self::SESSION_LAST_ACTION, $default);
    }

    /**
     * Sets the last action type to the session
     * @param string $type Type of the last action
     * @return null
     */
    public function setLastAction($type) {
        $session = $this->request->getSession();
        $session->set(self::SESSION_LAST_ACTION, $type);
    }

    /**
     * Gets the last region type which has been executed.
     *
     * This is used to if a user changes node in the UI, he will stay in the
     * same region if available
     * @param boolean isLayoutAvailable Flag to see if the layout is available
     * @return string|null Name of the region if a node has been opened, null
     * otherwise
     */
    public function getLastRegion($isLayoutAvailable) {
        if (!$this->request->hasSession()) {
            return null;
        }

        $session = $this->request->getSession();
        $region = null;

        if ($isLayoutAvailable && $session->get(self::SESSION_LAST_REGION . 'type') != 'theme') {
            $region = $session->get(self::SESSION_LAST_REGION . 'layout');
        }

        if (!$region) {
            $region = $session->get(self::SESSION_LAST_REGION . 'theme');
        }

        return $region;
    }

    /**
     * Sets the last region type to the session
     * @param string $region Name of the last region
     * @return null
     */
    public function setLastRegion($region, $type) {
        $session = $this->request->getSession();
        $session->set(self::SESSION_LAST_REGION . $type, $region);
        $session->set(self::SESSION_LAST_REGION . 'type', $type);
    }

}
