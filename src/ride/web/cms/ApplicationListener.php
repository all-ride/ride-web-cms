<?php

namespace ride\web\cms;

use ride\library\cache\control\CacheControl;
use ride\library\cms\node\Node;
use ride\library\event\EventManager;
use ride\library\event\Event;
use ride\library\http\Header;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\mvc\message\Message;
use ride\library\mvc\Request;
use ride\library\security\exception\UnauthorizedException;
use ride\library\security\SecurityManager;

use ride\web\base\controller\AbstractController;
use ride\web\base\menu\MenuItem;
use ride\web\base\menu\Menu;
use ride\web\mvc\view\JsonView;
use ride\web\mvc\view\TemplateView;
use ride\web\WebApplication;

/**
 * Event listener for the CMS application
 */
class ApplicationListener {

    /**
     * Name of the event to prepare the content menu
     * @var string
     */
    const EVENT_MENU_CONTENT = 'cms.menu.content';

    /**
     * Source for logging messages
     * @var string
     */
    const LOG_SOURCE = 'cms';

    /**
     * Cache controls to clear when the structure is updated
     * @var array
     */
    private $cacheControls = array();

    /**
     * Flag to see if a cache clear listener is registered
     * @var boolean
     */
    private $isCacheClearRegistered = false;

    /**
     * Sets the instance of the CMS
     * @param \ride\web\cms\Cms $cms Instance of the CMS facade
     * @return null
     */
    public function setCms(Cms $cms) {
        $this->cms = $cms;
    }

    /**
     * Sets the instance of the security manager
     * @param \ride\library\security\SecurityManager $securityManager
     * @return null
     */
    public function setSecurityManager(SecurityManager $securityManager) {
        $this->securityManager = $securityManager;
    }

    /**
     * Hook to prepare the CMS template views
     * @param \ride\library\event\Event $event Triggered event
     * @return null
     */
    public function prepareTemplateView(Event $event) {
        $web = $event->getArgument('web');
        $response = $web->getResponse();
        if (!$response) {
            return;
        }

        $view = $response->getView();
        if (!$view instanceof TemplateView) {
            return;
        }

        $template = $view->getTemplate();
        if (strpos($template->getResource(), 'cms/backend') !== 0) {
            return;
        }

        $node = $template->get('node');
        $site = $template->get('site');
        $locale = $template->get('locale');
        if (!$node || !$node->getId()) {
            if ($site) {
                $node = $site;
            } else {
                return;
            }
        } elseif (!$site) {
            $site = $node;
        }

        $parameters = array(
            'site' => $site->getRootNodeId(),
            'revision' => $site->getRevision(),
            'locale' => $locale,
        );
        $nodeCreateActions = array();

        $nodeTypes = $this->cms->getNodeTypes();
        if (isset($nodeTypes['site'])) {
            unset($nodeTypes['site']);
        }

        foreach ($nodeTypes as $nodeTypeName => $nodeType) {
            $url = $web->getUrl($nodeType->getRouteAdd(), $parameters);
            if ($this->securityManager->isUrlAllowed($url)) {
                $nodeCreateActions[$nodeTypeName] = $url;
            }
        }

        $breadcrumbs = $this->getBreadcrumbs($web, $node, $locale);
        $collapsedNodes = $this->getCollapsedNodes($node);

        $template->set('site', $site);
        $template->set('nodeCreateActions', $nodeCreateActions);
        $template->set('nodeActions', $this->cms->getActions($node, $locale));
        $template->set('collapsedNodes', json_encode($collapsedNodes));
        $template->set('breadcrumbs', $breadcrumbs);
    }

    /**
     * Gets the breadcrumbs to the current locale
     * @param \ride\web\WebApplication $web
     * @param \ride\library\cms\node\Node $node
     * @param string $locale
     * @return array Array with the URL to the node as key and the name of the
     * node as value
     */
    protected function getBreadcrumbs(WebApplication $web, Node $node, $locale) {
        $breadcrumbs = array();

        do {
            $url = (string) $web->getUrl('cms.node.default', array(
                'site' => $node->getRootNodeId(),
                'revision' => $node->getRevision(),
                'locale' => $locale,
                'node' => $node->getId(),
            ));

            $breadcrumbs[$url] = $node->getName($locale);

            $node = $node->getParentNode();
        } while ($node);

        return array_reverse($breadcrumbs, true);
    }

    /**
     * Gets the collapsed nodes for the provided node
     * @param \ride\library\cms\node\Node $node
     * @return array Array with the collapsed node id as key and true as value
     */
    protected function getCollapsedNodes(Node $node) {
        $site = $node->getRootNodeId();
        $revision = '[' . $node->getRevision() . ']';
        $nodes = array();

        $collapsedNodes = $this->cms->getCollapsedNodes();
        foreach ($collapsedNodes as $node => $flag) {
            if (strpos($node, $site) !== 0 || strpos($node, $revision) === false) {
                continue;
            }

            $node = str_replace($revision, '', $node);
            $tokens = explode(Node::PATH_SEPARATOR, $node);
            $nodes[array_pop($tokens)] = true;;
        }

        return $nodes;
    }

    /**
     * Action to add the CMS menus to the taskbar
     * @param \ride\library\event\Event $event Triggered event
     * @param \ride\library\i18n\I18N $i18n
     * @param \ride\web\WebApplication $web
     * @param \ride\library\event\EventManager $eventManager
     * @return null
     */
    public function prepareTaskbar(Event $event, I18n $i18n, WebApplication $web, EventManager $eventManager) {
        $locale = null;
        $request = $web->getRequest();
        $route = $request->getRoute();

        if ($route) {
            $locale = $route->getArgument('locale');
        }

        if (!$locale && $request->hasSession()) {
            $session = $request->getSession();
            $locale = $session->get(AbstractController::SESSION_LOCALE_CONTENT);
        }

        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();
        }

        $taskbar = $event->getArgument('taskbar');
        $applicationMenu = $taskbar->getApplicationsMenu();
        $referer = '?referer=' . urlencode($request->getUrl());

        // site menu
        $menu = new Menu();
        $menu->setId('sites.menu');
        $menu->setTranslation('label.sites');

        $defaultRevision = $this->cms->getDefaultRevision();
        $draftRevision = $this->cms->getDraftRevision();

        $sites = $this->cms->getSites();
        if ($sites) {
            foreach ($sites as $siteId => $site) {
                $availableLocales = $site->getAvailableLocales();
                if ($availableLocales == Node::LOCALES_ALL || isset($availableLocales[$locale])) {
                    $siteLocale = $locale;
                } else {
                    $siteLocale = reset($availableLocales);
                }

                if ($site->hasRevision($draftRevision)) {
                    $revision = $draftRevision;
                } elseif ($site->hasRevision($defaultRevision)) {
                    $revision = $defaultRevision;
                } else {
                    $revision = $site->getRevision();
                }

                $menuItem = new MenuItem();
                $menuItem->setLabel($site->getName($locale));
                $menuItem->setRoute('cms.site.detail.locale', array(
                    'site' => $site->getId(),
                    'revision' => $revision,
                    'locale' => $siteLocale,
                ));

                $menu->addMenuItem($menuItem);
            }
        }

        $url = $web->getUrl('cms.site.add', array(
            'locale' => $locale,
        )) . $referer;

        if ($this->securityManager->isUrlAllowed($url)) {
            if ($menu->hasItems()) {
                $menu->addSeparator();
            }

            $menuItem = new MenuItem();
            $menuItem->setTranslation('button.site.add');
            $menuItem->setUrl($url);

            $menu->addMenuItem($menuItem);
        }

        $applicationMenu->addMenu($menu);

        // theme menu
        $menu = new Menu();
        $menu->setId('themes.menu');
        $menu->setTranslation('label.themes');

        $themes = $this->cms->getThemes();
        if ($themes) {
            foreach ($themes as $theme) {
                $menuItem = new MenuItem();
                $menuItem->setLabel($theme->getDisplayName());
                $menuItem->setUrl($web->getUrl('cms.theme.edit', array(
                    'theme' => $theme->getName(),
                )) . $referer);

                $menu->addMenuItem($menuItem);
            }

            $menu->addSeparator();
        }

        $menuItem = new MenuItem();
        $menuItem->setTranslation('button.theme.add');
        $menuItem->setUrl($web->getUrl('cms.theme.add') . $referer);

        $menu->addMenuItem($menuItem);

        $applicationMenu->addMenu($menu);
    }

    /**
     * Sets a error view to the response if a status code above 399 is set
     * @return null
     */
    public function handleHttpError(Event $event, I18n $i18n) {
        $web = $event->getArgument('web');
        $request = $web->getRequest();
        $response = $web->getResponse();

        $route = $request->getRoute();
        if (!$route) {
            return;
        }

        $routeId = $route->getId();
        if ($routeId && substr($routeId, 0, 10) !== 'cms.front.') {
            // don't act on non cms pages
            return;
        }

        $statusCode = $response->getStatusCode();
        if (($statusCode != Response::STATUS_CODE_FORBIDDEN && $statusCode != Response::STATUS_CODE_NOT_FOUND) || ($request->isXmlHttpRequest() && $response->getView() instanceof JsonView)) {
            // js request or not a forbidden or not a not found status
            return;
        }

        $locale = $i18n->getLocale()->getCode();

        // lookup site with the current base URL
        $baseUrl = $request->getBaseUrl();
        $site = null;
        $defaultSite = null;

        $sites = $this->cms->getSites();
        foreach ($sites as $site) {
            if ($site->getBaseUrl($locale) == $baseUrl) {
                break;
            }

            $defaultSite = $site;
            $site = null;
        }

        if (!$site) {
            if (!$defaultSite) {
                return;
            }

            $site = $defaultSite;
        }

        // get the error node
        $node = $site->get('error.' . $statusCode);
        if (!$node) {
            // no node setup for this error
            return;
        }

        // resolve the locale
        $locales = $site->getAvailableLocales();
        if ($locales != Node::LOCALES_ALL && !isset($locales[$locale])) {
            $locale = array_pop($locales);
            $i18n->setCurrentLocale($locale);
        }

        // dispatch the error page
        $routerService = $web->getRouterService();
        $route = $routerService->getRouteById('cms.front.' . $site->getId() . '.' . $node . '.' . $locale);
        if (!$route) {
            $route = $routerService->getRouteById('cms.node.frontend.locale');
            $route->setArguments(array('node' => $node, 'locale' => $locale));
        } else {
            $route->setArguments();
        }

        $loginRequest = $web->createRequest($route->getPath());
        $loginRequest->setRoute($route);
        $loginRequest->getHeaders()->setHeader(Header::HEADER_REFERER, $request->getUrl());
        if ($request->hasSession()) {
            $loginRequest->setSession($request->getSession());
        }

        $response->getMessageContainer()->removeAll();
        $response->setView(null);
        $response->setStatusCode(Response::STATUS_CODE_OK);
        $response->removeHeader(Header::HEADER_CONTENT_TYPE);
        $response->clearRedirect();

        try {
            $dispatcher = $web->getDispatcher();
            $dispatcher->dispatch($loginRequest, $response);
        } catch (UnauthorizedException $exception) {
            if ($statusCode === Response::STATUS_CODE_FORBIDDEN) {
                $response->setRedirect($request->getBaseUrl());

                return;
            } else {
                throw $exception;
            }
        }

        $response->setStatusCode($statusCode);

        if ($loginRequest->hasSession()) {
            $request->setSession($loginRequest->getSession());
        }

        if ($statusCode === Response::STATUS_CODE_FORBIDDEN) {
            $response->addMessage(new Message($i18n->getTranslator()->translate('error.unauthorized'), Message::TYPE_ERROR));
        }
    }

    /**
     * Adds a cache control to clear when the CMS structure is updated
     * @param \ride\library\cache\control\CacheControl $cacheControl
     * @return null
     */
    public function addCacheControl(CacheControl $cacheControl) {
        $this->cacheControls[] = $cacheControl;
    }

    /**
     * Handles a node save or remove action
     * @param \ride\library\event\Event $event Save or remove event
     * @param \ride\library\event\EventManager $eventManager Instance of the
     * event manager
     * @return null
     */
    public function handleCache(Event $event, EventManager $eventManager) {
        if (!$this->cacheControls || $this->isCacheClearRegistered) {
            return;
        }

        if (!$this->isCacheClearRegistered) {
            // register event to commit when the controller has finished processing
            // the request
            $eventManager->addEventListener('app.response.pre', array($this, 'clearCache'));

            $this->isCacheClearRegistered = true;
        }
    }

    /**
     * Performs a cache clear for the cms
     * @param \ride\library\event\Event $event Pre response event
     * @return null
     */
    public function clearCache(Event $event) {
        foreach ($this->cacheControls as $cacheControl) {
            $cacheControl->clear();
        }

        $this->isCacheClearRegistered = false;
    }

}
