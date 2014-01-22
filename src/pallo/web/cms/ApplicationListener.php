<?php

namespace pallo\web\cms;

use pallo\library\cms\node\NodeModel;
use pallo\library\cms\node\Node;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\event\Event;
use pallo\library\i18n\I18n;
use pallo\library\mvc\Request;
use pallo\library\security\SecurityManager;

use pallo\web\base\view\MenuItem;
use pallo\web\base\view\Menu;
use pallo\web\cms\node\NodeTreeGenerator;
use pallo\web\mvc\view\TemplateView;
use pallo\web\WebApplication;

class ApplicationListener {

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

    public function prepareTaskbar(Event $event, Request $request, I18n $i18n, NodeModel $nodeModel, ThemeModel $themeModel, WebApplication $web, SecurityManager $securityManager) {
        $locale = $request->getRoute()->getArgument('locale');
        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();
        }

        $taskbar = $event->getArgument('taskbar');
        $applicationMenu = $taskbar->getApplicationsMenu();
        $referer = '?referer=' . urlencode($request->getUrl());

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

}