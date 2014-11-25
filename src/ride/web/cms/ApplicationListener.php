<?php

namespace ride\web\cms;

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
use ride\web\cms\node\tree\NodeTreeGenerator;
use ride\web\mvc\view\JsonView;
use ride\web\mvc\view\TemplateView;
use ride\web\WebApplication;

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

    public function prepareTemplateView(Event $event, Cms $cms, NodeTreeGenerator $nodeTreeGenerator, SecurityManager $securityManager) {
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

        $siteTreeNode = $nodeTreeGenerator->getTree($node, $locale);

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

        $template->set('site', $site);
        $template->set('siteTreeNode', $siteTreeNode);
        $template->set('nodeCreateActions', $nodeCreateActions);
    }

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

        // content menu
        $contentMenu = new Menu();
        $contentMenu->setId('content');
        $contentMenu->setTranslation('label.content');

        $eventManager->triggerEvent(self::EVENT_MENU_CONTENT, array('menu' => $contentMenu, 'locale' => $locale));

        $applicationMenu->addMenu($contentMenu);

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
     * Orders the items in the content menu
     * @param \ride\library\event\Event $event
     * @return null
     */
    public function processTaskbarMenu(Event $event) {
        $taskbar = $event->getArgument('taskbar');
        $applicationsMenu = $taskbar->getApplicationsMenu();

        $contentMenu = $applicationsMenu->getItem('content');
        if ($contentMenu) {
            $contentMenu->orderItems();
        }
    }

    /**
     * Sets a error view to the response if a status code above 399 is set
     * @return null
     */
    public function handleHttpError(Event $event, Cms $cms, I18n $i18n, WebApplication $web) {
        $request = $web->getRequest();
        $response = $web->getResponse();

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
        if (!isset($locales[$locale])) {
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

}
