<?php

namespace ride\web\cms;

use ride\library\cache\pool\CachePool;
use ride\library\cms\node\Node;
use ride\library\event\EventManager;
use ride\library\event\Event;
use ride\library\http\Header;
use ride\library\http\Response;
use ride\library\i18n\I18n;
use ride\library\mvc\message\Message;
use ride\library\mvc\Request;
use ride\library\security\SecurityManager;

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
     * Instance of the CMS cache
     * @var \ride\library\cache\pool\CachePool
     */
    private $cache = null;

    /**
     * Flag to see if a cache clear listener is registered
     * @var boolean
     */
    private $isCacheClearRegistered = false;

    /**
     * Hook to prepare the CMS template views
     * @param \ride\library\event\Event $event Triggered event
     * @param \ride\web\cms\Cms $cms Instance of the CMS facade
     * @param \ride\library\security\SecurityManager $securityManager
     * @return null
     */
    public function prepareTemplateView(Event $event, Cms $cms, SecurityManager $securityManager) {
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

        $nodeTypes = $cms->getNodeTypes();
        foreach ($nodeTypes as $nodeTypeName => $nodeType) {
            $url = $web->getUrl($nodeType->getRouteAdd(), $parameters);
            if ($securityManager->isUrlAllowed($url)) {
                $nodeCreateActions[$nodeTypeName] = $url;
            }
        }

        $breadcrumbs = $this->getBreadcrumbs($web, $cms, $node, $locale);
        $collapsedNodes = $this->getCollapsedNodes($cms, $node);

        $template->set('site', $site);
        $template->set('nodeCreateActions', $nodeCreateActions);
        $template->set('collapsedNodes', json_encode($collapsedNodes));
        $template->set('breadcrumbs', $breadcrumbs);
    }

    /**
     * Gets the breadcrumbs to the current locale
     * @param \ride\web\WebApplication $web
     * @param \ride\web\cms\Cms $cms
     * @param \ride\library\cms\node\Node $node
     * @param string $locale
     * @return array Array with the URL to the node as key and the name of the
     * node as value
     */
    protected function getBreadcrumbs(WebApplication $web, Cms $cms, Node $node, $locale) {
        $breadcrumbs = array();

        do {
            $url = $web->getUrl('cms.node.default', array(
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
     * @param \ride\web\cms\Cms $cms
     * @param \ride\library\cms\node\Node $node
     * @return array Array with the collapsed node id as key and true as value
     */
    protected function getCollapsedNodes(Cms $cms, Node $node) {
        $site = $node->getRootNodeId();
        $revision = '[' . $node->getRevision() . ']';
        $nodes = array();

        $collapsedNodes = $cms->getCollapsedNodes();
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
     * @param \ride\web\cms\Cms $cms Instance of the CMS facade
     * @param \ride\library\i18n\I18N $i18n
     * @param \ride\web\WebApplication $web
     * @param \ride\library\security\SecurityManager $securityManager
     * @param \ride\library\event\EventManager $eventManager
     * @return null
     */
    public function prepareTaskbar(Event $event, Cms $cms, I18n $i18n, WebApplication $web, SecurityManager $securityManager, EventManager $eventManager) {
        $locale = null;
        $request = $web->getRequest();
        $route = $request->getRoute();

        if ($route) {
            $locale = $route->getArgument('locale');
        }

        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();
        }

        $taskbar = $event->getArgument('taskbar');
        $applicationMenu = $taskbar->getApplicationsMenu();
        $referer = '?referer=' . urlencode($request->getUrl());

        // site menu
        $menu = new Menu();
        $menu->setTranslation('label.sites');

        $defaultRevision = $cms->getDefaultRevision();
        $draftRevision = $cms->getDraftRevision();

        $sites = $cms->getSites();
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

        if ($securityManager->isUrlAllowed($url)) {
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
        $menu->setTranslation('label.themes');

        $themes = $cms->getThemes();
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
    public function handleHttpError(Event $event, Cms $cms, I18n $i18n, WebApplication $web) {
        $request = $web->getRequest();
        $response = $web->getResponse();

        $routeId = $request->getRoute()->getId();
        if ($routeId && substr($routeId, 0, 10) !== 'cms.front.') {
            // don't act on non cms pages
            return;
        }

        $statusCode = $response->getStatusCode();
        if (($statusCode != Response::STATUS_CODE_FORBIDDEN && $statusCode != Response::STATUS_CODE_NOT_FOUND) || ($request->isXmlHttpRequest() && $response->getView() instanceof JsonView)) {
            return;
        }

        $locale = $i18n->getLocale()->getCode();

        // lookup site with the current base URL
        $baseUrl = $request->getBaseUrl();
        $site = null;
        $defaultSite = null;

        $sites = $cms->getSites();
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
            return;
        }

        // resolve the locale
        $locales = $site->getAvailableLocales();
        if ($locales != Node::LOCALES_ALL && !isset($locales[$locale])) {
            $locale = array_pop($locales);
            $i18n->setCurrentLocale($locale);

            $message = $i18n->getTranslator()->translate('error.unauthorized');

            $response->getMessageContainer()->removeAll();
            $response->addMessage(new Message($message, Message::TYPE_ERROR));
        }

        // dispatch the error page
        $routeContainer = $web->getRouter()->getRouteContainer();
        $route = $routeContainer->getRouteById('cms.front.' . $site->getId() . '.' . $node . '.' . $locale);
        if (!$route) {
            $route = $routeContainer->getRouteById('cms.node.frontend.locale');
            $route->setArguments(array('node' => $node, 'locale' => $locale));
        } else {
            $route->setArguments();
        }

        $request = $web->createRequest($route->getPath());
        $request->setRoute($route);

        $response->setView(null);
        $response->setStatusCode(Response::STATUS_CODE_OK);
        $response->removeHeader(Header::HEADER_CONTENT_TYPE);
        $response->clearRedirect();

        $dispatcher = $web->getDispatcher();
        $dispatcher->dispatch($request, $response);

        $response->setStatusCode($statusCode);
    }

    /**
     * Sets the cache pool of the cms
     * @param \ride\library\cache\pool\CachePool $cache
     * @return null
     */
    public function setCache(CachePool $cache) {
        $this->cache = $cache;
    }

    /**
     * Handles a node save or remove action
     * @param \ride\library\event\Event $event Save or remove event
     * @param \ride\library\event\EventManager $eventManager Instance of the
     * event manager
     * @return null
     */
    public function handleCache(Event $event, EventManager $eventManager) {
        if (!$this->cache || $this->isCacheClearRegistered) {
            return;
        }

        if (!$this->isCacheClearRegistered) {
            // register event to commit when the controller has finished processing
            // the request
            $eventManager->addEventListener('app.response.post', array($this, 'clearCache'));

            $this->isCacheClearRegistered = true;
        }
    }

    /**
     * Performs a cache clear for the cms
     * @param \ride\library\event\Event $event Pre response event
     * @return null
     */
    public function clearCache(Event $event) {
        $this->cache->flush();

        $this->isCacheClearRegistered = false;
    }

}
