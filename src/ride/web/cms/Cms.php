<?php

namespace ride\web\cms;

use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\NodeModel;
use ride\library\cms\node\NodeProperty;
use ride\library\cms\node\Node;
use ride\library\cms\theme\ThemeModel;
use ride\library\cms\widget\WidgetModel;
use ride\library\cms\Cms as LibraryCms;
use ride\library\http\Response;
use ride\library\i18n\translator\Translator;
use ride\library\i18n\I18n;
use ride\library\security\SecurityManager;

use ride\web\cms\controller\backend\action\node\NodeAction;
use ride\web\WebApplication;

/**
 * Facade for the CMS
 */
class Cms extends LibraryCms {

    /**
     * Value for the inherited value
     * @var string
     */
    const OPTION_INHERITED = 'inherited';

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
    const SESSION_LAST_REGION = 'cms.region.last';

    /**
     * Constructs a new CMS facade
     * @param \ride\web\WebApplication $web
     * @param \ride\library\i18n\I18n $i18n
     * @param \ride\library\cms\node\NodeModel $nodeModel
     * @param \ride\library\cms\theme\ThemeModel $themeModel
     * @param \ride\library\cms\layout\LayoutModel $layoutModel
     * @param \ride\library\cms\widget\WidgetModel $widgetModel
     * @param \ride\library\security\SecurityManager $securityManager
     * @return null
     */
    public function __construct(WebApplication $web, I18n $i18n, NodeModel $nodeModel, ThemeModel $themeModel, LayoutModel $layoutModel, WidgetModel $widgetModel, SecurityManager $securityManager) {
        parent::__construct($nodeModel, $themeModel, $layoutModel, $widgetModel, $securityManager);

        $this->web = $web;
        $this->request = $web->getRequest();
        $this->i18n = $i18n;
        $this->actions = array();
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
            $this->web->getResponse()->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
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
            $this->web->getResponse()->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
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

        $this->web->getRequest()->getSession()->set(self::SESSION_COLLAPSED_NODES, $collapsedNodes);

        $user = $this->securityManager->getUser();
        if ($user) {
            $user->setPreference(self::SESSION_COLLAPSED_NODES, $collapsedNodes);

            $this->securityManager->getSecurityModel()->saveUser($user);
        }

        return $isCollapsed;
    }

    /**
     * Gets the collapsed nodes
     * @return array Array with the %path%[%revision%] of the node as key, and
     * a boolean as value
     */
    public function getCollapsedNodes() {
        $user = $this->securityManager->getUser();
        if ($user) {
            $collapsedNodes = $user->getPreference(self::SESSION_COLLAPSED_NODES, array());
        } else {
            $collapsedNodes = array();
        }

        $request = $this->web->getRequest();
        if ($request->hasSession()) {
            $collapsedNodes = $request->getSession()->get(self::SESSION_COLLAPSED_NODES, $collapsedNodes);
        }

        return $collapsedNodes;
    }

    /**
     * Sets the node actions
     * @param array $actions Array with the name of the action as key and an
     * instance of the action as value
     * @return null
     */
    public function setActions(array $actions) {
        foreach ($actions as $actionName => $action) {
            $this->setAction($actionName, $action);
        }
    }

    /**
     * Sets a node action
     * @param string $name Name of the action
     * @param \ride\web\cms\controller\backend\action\node\NodeAction $action
     * @return null
     */
    public function setAction($name, NodeAction $action) {
        $this->actions[$name] = $action;
    }

    /**
     * Gets the node actions for the provided node
     * @param \ride\library\cms\node\Node $node
     * @param string $locale
     * @return array Array with the name of the action as key and the URL as
     * value
     */
    public function getActions(Node $node, $locale) {
        $actions = array();
        $urlVars = array(
            'site' => $node->getRootNodeId(),
            'revision' => $node->getRevision(),
            'node' => $node->getId(),
            'locale' => $locale,
        );
        foreach ($this->actions as $actionName => $action) {
            if (!$action->isAvailableForNode($node)) {
                continue;
            }

            $actionUrl = $this->web->getUrl($action->getRoute(), $urlVars);
            if (!$this->securityManager->isUrlAllowed($actionUrl)) {
                continue;
            }

            $actions[$actionName] = $actionUrl;
        }

        return $actions;
    }

    /**
     * Gets the last action type which has been executed.
     *
     * This is used to if a user changes node in the UI, he will stay in the
     * same type of backend page. For example, when the user is in the advanced
     * page, he will come in the advanced page of the other node
     * @param string $default Default action to return when the last action is
     * not set
     * @return string|null Action type if a node has been opened, provided
     * default value otherwise
     */
    public function getLastAction($default = null) {
        $request = $this->web->getRequest();
        if (!$request->hasSession()) {
            return $default;
        }

        return $request->getSession()->get(self::SESSION_LAST_ACTION, $default);
    }

    /**
     * Sets the last action type to the session
     * @param string $type Type of the last action
     * @return null
     */
    public function setLastAction($type) {
        $session = $this->web->getRequest()->getSession();
        $session->set(self::SESSION_LAST_ACTION, $type);
    }

    /**
     * Gets the last region type which has been executed.
     *
     * This is used to if a user changes node in the UI, he will stay in the
     * same region if available
     * @param string $default Default region to return when the last region is
     * not set
     * @return string|null Name of the region if set, provided default value
     * otherwise
     */
    public function getLastRegion($default = null) {
        $request = $this->web->getRequest();
        if (!$request->hasSession()) {
            return $default;
        }

        return $request->getSession()->get(self::SESSION_LAST_REGION, $default);
    }

    /**
     * Sets the last region type to the session
     * @param string $region Name of the last region
     * @return null
     */
    public function setLastRegion($region) {
        $session = $this->web->getRequest()->getSession();
        $session->set(self::SESSION_LAST_REGION, $region);
    }

    /**
     * Processes the inherited value for the provided option value
     * @param mixed $value Submitted value
     * @return mixed Null for an inherited option, submitted values otherwise
     */
    public function getOptionValueFromForm($value) {
        if ($value == self::OPTION_INHERITED || (is_array($value) && in_array(self::OPTION_INHERITED, $value))) {
            return null;
        }

        return $value;
    }

    /**
     * Gets the form value for the theme option
     * @param \ride\library\cms\node\Node $node
     * @return string
     */
    public function getThemeValueFromNode(Node $node) {
        $theme = $node->get(Node::PROPERTY_THEME, null, false);
        if (!$theme && $node->hasParent()) {
            $theme = self::OPTION_INHERITED;
        }

        return $theme;
    }

    /**
     * Gets the available theme options
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\i18n\translator\Translator $translator
     * @param array $themes
     * @return array Array with the machine name as key and the display name as
     * value
     */
    public function getThemeOptions(Node $node, Translator $translator, array $themes) {
        $options = array();

        $parentNode = $node->getParentNode();
        if ($parentNode) {
            $inheritedValue = $parentNode->get(Node::PROPERTY_THEME, null, true, true);

            if (isset($themes[$inheritedValue])) {
                $inheritedValue = $themes[$inheritedValue]->getDisplayName();
            }

            $options[self::OPTION_INHERITED] = $translator->translate('label.inherited') . ' (' . $inheritedValue . ')';
        }

        foreach ($themes as $id => $theme) {
            $options[$id] = $theme->getDisplayName();
        }

        return $options;
    }

    /**
     * Gets the form value for the available locales options
     * @param \ride\library\cms\node\Node $node
     * @return array
     */
    public function getLocalesValue($availableLocales, $hasParent) {
        $value = array();

        if ($availableLocales == Node::LOCALES_ALL || (!$availableLocales && !$hasParent)) {
            $value[Node::LOCALES_ALL] = Node::LOCALES_ALL;
        } elseif ($availableLocales && $availableLocales != Node::LOCALES_ALL) {
            $locales = explode(NodeProperty::LIST_SEPARATOR, $availableLocales);

            $value = array();
            foreach ($locales as $locale) {
                $locale = trim($locale);

                $value[$locale] = $locale;
            }
        } else {
            $value[self::OPTION_INHERITED] = self::OPTION_INHERITED;
        }

        return $value;
    }

    /**
     * Gets the available locales options
     * @param \ride\library\i18n\translator\Translator $translator
     * @param array $locales
     * @param \ride\library\cms\node\Node $node Node containing inherited locales
     * @return array Array with the publish code as key and the translation as
     * value
     */
    public function getLocalesOptions(Translator $translator, array $locales, Node $node = null) {
        $options = array();

        if ($node) {
            $inheritedValue = $node->get(Node::PROPERTY_LOCALES, Node::LOCALES_ALL);

            $options[self::OPTION_INHERITED] = $translator->translate('label.inherited') . ' (' . $inheritedValue . ')';
        }

        $options[Node::LOCALES_ALL] = $translator->translate('label.locales.all');
        foreach ($locales as $locale) {
            $options[$locale] = $translator->translate('language.' . $locale);
        }

        return $options;
    }

}
