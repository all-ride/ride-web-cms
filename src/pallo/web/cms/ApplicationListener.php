<?php

namespace pallo\web\cms;

use pallo\library\cms\node\NodeModel;
use pallo\library\cms\theme\ThemeModel;
use pallo\library\event\Event;
use pallo\library\i18n\I18n;
use pallo\library\mvc\Request;

use pallo\web\base\view\MenuItem;
use pallo\web\base\view\Menu;
use pallo\web\cms\node\NodeTreeGenerator;
use pallo\web\mvc\view\TemplateView;

class ApplicationListener {

    const LOG_SOURCE = 'cms';

    public function prepareTemplateView(Event $event, I18n $i18n, NodeModel $nodeModel, NodeTreeGenerator $nodeTreeGenerator) {
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

        $nodeTree = $nodeTreeGenerator->getTreeHtml($node, $locale);

        $template->set('site', $site);
        $template->set('nodeTree', $nodeTree);
        $template->set('nodeTypes', $nodeModel->getNodeTypeManager()->getNodeTypes());
    }

    public function prepareTaskbar(Event $event, Request $request, I18n $i18n, NodeModel $nodeModel, ThemeModel $themeModel) {
        $locale = $request->getRoute()->getArgument('locale');
        if (!$locale) {
            $locale = $i18n->getLocale()->getCode();
        }

        $taskbar = $event->getArgument('taskbar');
        $applicationMenu = $taskbar->getApplicationsMenu();

        // site menu
        $menu = new Menu();
        $menu->setTranslation('label.sites');

        $sites = $nodeModel->getNodesByType('site');
        if ($sites) {
            foreach ($sites as $nodeId => $node) {
                $menuItem = new MenuItem();
                $menuItem->setLabel($node->getName($locale));
                $menuItem->setRoute('cms.site.detail.locale', array(
                    'site' => $node->getId(),
                    'locale' => $locale,
                ));

                $menu->addMenuItem($menuItem);
            }

            $menu->addSeparator();
        }

        $menuItem = new MenuItem();
        $menuItem->setTranslation('button.site.add');
        $menuItem->setRoute('cms.site.add', array(
            'locale' => $locale,
        ));

        $menu->addMenuItem($menuItem);

        $applicationMenu->addMenu($menu);

        // theme menu
        $menu = new Menu();
        $menu->setTranslation('label.themes');

        $themes = $themeModel->getThemes();
        if ($themes) {
            foreach ($themes as $theme) {
                $menuItem = new MenuItem();
                $menuItem->setLabel($theme->getDisplayName());
                $menuItem->setRoute('cms.theme.edit', array(
                    'theme' => $theme->getName(),
                ));

                $menu->addMenuItem($menuItem);
            }

            $menu->addSeparator();
        }

        $menuItem = new MenuItem();
        $menuItem->setTranslation('button.theme.add');
        $menuItem->setRoute('cms.theme.add');

        $menu->addMenuItem($menuItem);

        $applicationMenu->addMenu($menu);
    }

}