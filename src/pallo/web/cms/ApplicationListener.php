<?php

namespace pallo\web\cms;

use pallo\library\cms\node\NodeModel;
use pallo\library\cms\node\Node;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\event\EventManager;
use pallo\library\event\Event;
use pallo\library\http\Response;
use pallo\library\i18n\I18n;
use pallo\library\mvc\Request;
use pallo\library\security\SecurityManager;

use pallo\web\base\view\MenuItem;
use pallo\web\base\view\Menu;
use pallo\web\cms\node\NodeTreeGenerator;
use pallo\web\mvc\view\TemplateView;
use pallo\web\WebApplication;
use pallo\web\cms\node\type\SiteNodeType;

class ApplicationListener {

    /**
     * Name of the event to prepare the content menu
     * @var string
     */
    const EVENT_MENU_CONTENT = 'cms.menu.content';

    const LOG_SOURCE = 'cms';

    public function prepareTemplateView(Event $event, I18n $i18n, NodeModel $nodeModel, NodeTreeGenerator $nodeTreeGenerator, SecurityManager $securityManager) {
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

        $translator = $i18n->getTranslator();

        $nodeTypes = $nodeModel->getNodeTypeManager()->getNodeTypes();
        $nodeTree = $nodeTreeGenerator->getTreeHtml($node, $locale);
        $nodeCreateActions = array();

        $parameters = array(
            'locale' => $locale,
            'site' => $site->getRootNodeId(),
        );
        foreach ($nodeTypes as $nodeTypeName => $null) {
            $url = $web->getUrl('cms.' . $nodeTypeName . '.add', $parameters);
            if ($securityManager->isUrlAllowed($url)) {
                $nodeCreateActions[$nodeTypeName] = $url;
            }
        }

        $template->set('site', $site);
        $template->set('nodeTree', $nodeTree);
        $template->set('nodeTypes', $nodeTypes);
        $template->set('nodeCreateActions', $nodeCreateActions);
    }

    public function prepareTaskbar(Event $event, Request $request, I18n $i18n, NodeModel $nodeModel, ThemeModel $themeModel, WebApplication $web, SecurityManager $securityManager, EventManager $eventManager) {
        $locale = $request->getRoute()->getArgument('locale');
        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();
        }

        $taskbar = $event->getArgument('taskbar');
        $applicationMenu = $taskbar->getApplicationsMenu();
        $referer = '?referer=' . urlencode($request->getUrl());

        // content menu
        $contentMenu = new Menu();
        $contentMenu->setTranslation('label.content');

        $eventManager->triggerEvent(self::EVENT_MENU_CONTENT, array('menu' => $contentMenu, 'locale' => $locale));

        $applicationMenu->addMenu($contentMenu);

        // site menu
        $menu = new Menu();
        $menu->setTranslation('label.sites');

        $sites = $nodeModel->getNodesByType('site');
        if ($sites) {
            foreach ($sites as $nodeId => $node) {
                $availableLocales = $node->getAvailableLocales();
                if ($availableLocales == Node::LOCALES_ALL || isset($availableLocales[$locale])) {
                    $siteLocale = $locale;
                } else {
                    $siteLocale = each($availableLocales);
                    $siteLocale = $siteLocale['value'];
                }

                $menuItem = new MenuItem();
                $menuItem->setLabel($node->getName($locale));
                $menuItem->setRoute('cms.site.detail.locale', array(
                    'site' => $node->getId(),
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

        $themes = $themeModel->getThemes();
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
    public function handleHttpError(Event $event, WebApplication $web, I18n $i18n, NodeModel $nodeModel) {
        $response = $web->getResponse();

        $statusCode = $response->getStatusCode();
        if (($statusCode != 403 && $statusCode != 404) || $response->getView() || $response->getBody()) {
            return;
        }

        $locale = $i18n->getLocale()->getCode();

        // lookup site with the current base URL
        $baseUrl = $web->getRequest()->getBaseUrl();
        $site = null;
        $defaultSite = null;

        $sites = $nodeModel->getNodesByType(SiteNodeType::NAME);
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

        // dispatch the error page
        $routeContainer = $web->getRouter()->getRouteContainer();
        $route = $routeContainer->getRouteById('cms.front.' . $node . '.' . $locale);
        $route->setArguments();

        $request = $web->createRequest($route->getPath());
        $request->setRoute($route);

        $response->setView(null);
        $response->setStatusCode(Response::STATUS_CODE_OK);
        $response->clearRedirect();

        $dispatcher = $web->getDispatcher();
        $dispatcher->dispatch($request, $response);

        $response->setStatusCode(Response::STATUS_CODE_NOT_FOUND);
    }

}