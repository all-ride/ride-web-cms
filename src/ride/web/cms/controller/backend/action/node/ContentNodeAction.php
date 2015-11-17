<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\layout\Layout;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeProperty;
use ride\library\cms\theme\Theme;
use ride\library\security\exception\UnauthorizedException;
use ride\library\security\SecurityManager;

use ride\web\cms\controller\backend\action\widget\WidgetActionManager;
use ride\web\cms\Cms;
use ride\web\mvc\controller\AbstractController;

/**
 * Controller of the content node action
 */
class ContentNodeAction extends AbstractNodeAction {

    /**
     * Name of this action
     * @var string
     */
    const NAME = 'content';

    /**
     * Route of this action
     * @var string
     */
    const ROUTE = 'cms.node.content';

    /**
     * Name of the default layout
     * @var string
     */
    protected $defaultLayout = '100';

    /**
     * Sets the default layout
     * @param $defaultLayout Machine name of the default layout
     * @return null
     */
    public function setDefaultLayout($defaultLayout) {
        $this->defaultLayout = $defaultLayout;
    }

    /**
     * Detects the current region and redirects to the region page
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @return null
     */
    public function indexAction(Cms $cms, $locale, $site, $revision, $node) {
        if (!$cms->resolveNode($site, $revision, $node)) {
            return;
        }

        $this->setContentLocale($locale);
        $cms->setLastAction(self::NAME);

        $theme = $cms->getTheme($node->getTheme());
        $securityManager = $this->getSecurityManager();

        $form = $this->buildRegionForm($securityManager, $node, $theme);
        if ($form->isSubmitted()) {
            $data = $form->getData();

            $region = $data['region'];
        } else {
            $region = $cms->getLastRegion();
        }

        if ($region && !$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            $region = null;
        }

        if (!$region || ($region && !$theme->hasRegion($region))) {
            $region = null;

            $regions = $this->getRegions($securityManager, $theme);
            if ($regions) {
                $region = array_shift($regions);
            }
        }

        if (!$region) {
            throw new UnauthorizedException();
        }

        $this->response->setRedirect($this->getUrl('cms.node.content.region', array(
            'locale' => $locale,
            'site' => $site->getId(),
            'revision' => $node->getRevision(),
            'node' => $node->getId(),
            'region' => $region,
        )));
    }

    /**
     * Action to show the region editor
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function regionAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $regions = $theme->getRegions();
        $availableWidgets = $this->getWidgets($cms);

        $cms->setLastAction(self::NAME);
        $cms->setLastRegion($region);

        $form = $this->buildRegionForm($securityManager, $node, $theme, $region);

        $regionWidgets = array();
        $inheritedRegionWidgets = array();

        $sections = $node->getSections($region);
        if (!$sections) {
            $section = $node->addSection($region, $this->defaultLayout);

            $cms->saveNode($node, 'Added section ' . $section . ' to region ' . $region . ' on node ' . $node->getName());

            $sections[0] = $this->defaultLayout;
            $regionWidgets[0] = array();
            $inheritedRegionWidgets[0] = array();
        }

        foreach ($sections as $section => $layout) {
            $regionWidgets[$section] = array();
            $inheritedRegionWidgets[$section] = array();

            $this->processSectionWidgets($node, $locale, $region, $section, $availableWidgets, $regionWidgets[$section], $inheritedRegionWidgets[$section]);
        }

        $baseAction = $this->getUrl('cms.node.content.region', array(
            'locale' => $locale,
            'site' => $site->getId(),
            'revision' => $node->getRevision(),
            'node' => $node->getId(),
            'region' => $region,
        ));

        $layouts = $cms->getLayouts();
        if (!$layouts) {
            $this->addWarning('warning.layouts.none');
        }

        $this->setTemplateView('cms/backend/node.content', array(
            'site' => $site,
            'node' => $node,
            'form' => $form->getView(),
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'layouts' => $layouts,
            'sections' => $sections,
            'regionWidgets' => $regionWidgets,
            'inheritedWidgets' => $inheritedRegionWidgets,
            'availableWidgets' => $availableWidgets,
            'actions' => $widgetActionManager->getWidgetActions(),
            'baseAction' => $baseAction,
        ));
    }

    /**
     * Action to reorder the sections and widgets of a region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function orderAction(Cms $cms, $locale, $site, $revision, $node, $region) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $order = $this->request->getBodyParameter('order', array());
        foreach ($order as $section => $blocks) {
            $order[str_replace('section', '', $section)] = $blocks;
            unset($order[$section]);
        }

        $node->orderSections($region, $order);

        $cms->saveNode($node, 'Reordered sections and widgets from region ' . $region . ' on node ' . $node->getName());
    }

    /**
     * Action to show the HTML from a section
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\action\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $section Name of the section
     * @return null
     */
    public function sectionAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region, $section) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $this->setSectionView($cms, $widgetActionManager, $site, $node, $locale, $region, $section);
    }

    /**
     * Action to add a new section to the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function sectionAddAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $section = $node->addSection($region, $this->defaultLayout);

        $cms->saveNode($node, 'Added section ' . $section . ' to region ' . $region . ' on node ' . $node->getName());

        $this->setSectionView($cms, $widgetActionManager, $site, $node, $locale, $region, $section);
    }

    /**
     * Action to update the layout of a section
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $section Name of the section
     * @param string $layout Name of the layout
     * @return null
     */
    public function sectionLayoutAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region, $section, $layout) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $node->setSectionLayout($region, $section, $layout);

        $cms->saveNode($node, 'Set layout of section ' . $section . ' from region ' . $region . ' on node ' . $node->getName() . ' to ' . $layout);

        $this->setSectionView($cms, $widgetActionManager, $site, $node, $locale, $region, $section);
    }

    /**
     * Action to dispatch to the properties of a section
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @param string $region
     * @param string $widget
     * @return null
     */
    public function sectionPropertiesAction(Cms $cms, $locale, $site, $revision, $node, $region, $section) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $translator = $this->getTranslator();

        $data = array(
            'title' => $node->getSectionTitle($region, $section, $locale),
            'isFullWidth' => $node->isSectionFullWidth($region, $section),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('title', 'string', array(
            'label' => $translator->translate('label.title'),
        ));
        $form->addRow('isFullWidth', 'option', array(
            'label' => $translator->translate('label.section.width.full'),
            'description' => $translator->translate('label.section.width.full.description'),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setSectionTitle($region, $section, $locale, $data['title']);
                $node->setIsSectionFullWidth($region, $section, $data['isFullWidth']);

                $cms->saveNode($node, 'Updated properties for section ' . $section . ' in region ' . $region . ' of node ' . $node->getName());

                $this->response->setRedirect($this->getUrl(
                    'cms.node.content.region',
                    array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'revision' => $node->getRevision(),
                        'node' => $node->getId(),
                        'region' => $region,
                    )
                ));

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/section.properties', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'section' => $section,
            'form' => $form->getView(),
        ));
    }

    /**
     * Action to dispatch to the style of a section
     * @param \ride\web\cms\Cms $cms
     * @param string $locale
     * @param string $site
     * @param string $revision
     * @param string $node
     * @param string $region
     * @param string $widget
     * @return null
     */
    public function sectionStyleAction(Cms $cms, $locale, $site, $revision, $node, $region, $section) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $translator = $this->getTranslator();

        $data = array(
            'style' => $node->getSectionStyle($region, $section),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('style', 'string', array(
            'label' => $translator->translate('label.style.container'),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $node->setSectionStyle($region, $section, $data['style']);

                $cms->saveNode($node, 'Updated style for section ' . $section . ' in region ' . $region . ' of node ' . $node->getName());

                $this->response->setRedirect($this->getUrl(
                    'cms.node.content.region',
                    array(
                        'locale' => $locale,
                        'site' => $site->getId(),
                        'revision' => $node->getRevision(),
                        'node' => $node->getId(),
                        'region' => $region,
                    )
                ));

                return;
            } catch (ValidationException $validationException) {
                $this->setValidationException($validationException, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->setTemplateView('cms/backend/section.style', array(
            'site' => $site,
            'node' => $node,
            'referer' => $referer,
            'locale' => $locale,
            'locales' => $cms->getLocales(),
            'region' => $region,
            'section' => $section,
            'form' => $form->getView(),
        ));
    }

    /**
     * Action to delete the definition of a section
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $section Name of the section
     * @return null
     */
    public function sectionDeleteAction(Cms $cms, $locale, $site, $revision, $node, $region, $section) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $section = $node->deleteSection($region, $section);

        $cms->saveNode($node, 'Deleted section ' . $section . ' from region ' . $region . ' on node ' . $node->getName());
    }

    /**
     * Action to add a widget to the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $widget Id of the widget instance
     * @return null
     */
    public function widgetAddAction(Cms $cms, WidgetActionManager $widgetActionManager, $locale, $site, $revision, $node, $region, $section, $block, $widget) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        } elseif (!$securityManager->isPermissionGranted('cms.widget.' . $widget . '.manage')) {
            throw new UnauthorizedException();
        }

        $widgetId = $site->createWidget($widget);
        $node->addWidget($region, $section, $block, $widgetId);

        $cms->saveNode($site, 'Created new instance for widget ' . $widget . ' in ' . $site->getName());
        $cms->saveNode($node, 'Added widget ' . $widget . ' to ' . $node->getName());

        $widget = clone $cms->getWidget($widget);
        $widget->setProperties($node->getWidgetProperties($widgetId));
        $widget->setLocale($locale);
        $widget->setRegion($region);
        $widget->setSection($section);
        $widget->setBlock($block);

        if ($widget instanceof AbstractController) {
            $widget->setConfig($this->config);
            $widget->setDependencyInjector($this->dependencyInjector);
        }

        $this->setTemplateView('cms/backend/widget.content', array(
            'locale' => $locale,
            'site' => $site,
            'node' => $node,
            'region' => $region,
            'section' => $section,
            'block' => $block,
            'widget' => $widget,
            'widgetId' => $widgetId,
            'inheritedWidgets' => array(),
            'actions' => $widgetActionManager->getWidgetActions(),
        ));
    }

    /**
     * Action to delete a widget from the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @param string $section Name of the section
     * @param string $block Name of the block
     * @param string $widget Id of the widget instance
     * @return null
     */
    public function widgetDeleteAction(Cms $cms, $locale, $site, $revision, $node, $region, $section, $block, $widget) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $widgetClass = $site->getWidget($widget);
        if (!$securityManager->isPermissionGranted('cms.widget.' . $widgetClass . '.manage')) {
            throw new UnauthorizedException();
        }

        $node->deleteWidget($region, $section, $block, $widget);

        $cms->saveNode($node, 'Deleted widget ' . $widget . ' from region ' . $region . ' in block ' . $block . ' of section ' . $section . ' on node ' . $node->getName());
    }

    /**
     * Action to reorder the widgets of the provided region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function widgetOrderAction(Cms $cms, $locale, $site, $revision, $node, $region, $section, $block) {
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region)) {
            return;
        }

        $securityManager = $this->getSecurityManager();
        if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
            throw new UnauthorizedException();
        }

        $widgetsValue = $this->request->getQueryParameter('widgets');
        $widgetsValue = rtrim(trim($widgetsValue), NodeProperty::LIST_SEPARATOR);

        $node->orderWidgets($region, $section, $block, $widgetsValue);

        $cms->saveNode($node, 'Reordered widgets on ' . $node->getName());
    }

    /**
     * Creates a form to select the region
     * @param \ride\library\cms\node\Node $node
     * @param \ride\library\cms\theme\Theme $theme
     * @param string $region
     * @return \ride\library\form\Form
     */
    protected function buildRegionForm(SecurityManager $securityManager, Node $node, Theme $theme = null, $region = null) {
        $regions = $this->getRegions($securityManager, $theme);

        $form = $this->createFormBuilder(array('region' => $region));
        $form->setId('form-region-select');
        $form->addRow('region', 'select', array(
            'options' => $regions,
        ));

        return $form->build();
    }

    /**
     * Sets the detail view of a section to the response
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param \ride\web\cms\controller\backend\actino\widget\WidgetActionManager $widgetActionManager
     * @param \ride\library\cms\node\Node $site Instance of the site
     * @param \ride\library\cms\node\Node $node Instance of the node
     * @param string $locale Code of the locale
     * @param string $region Name of the region
     * @param string $section Name of the section
     * @return null
     */
    protected function setSectionView(Cms $cms, WidgetActionManager $widgetActionManager, Node $site, Node $node, $locale, $region, $section) {
        $availableWidgets = $cms->getWidgets();
        $widgets = array();
        $inheritedWidgets = array();

        $this->processSectionWidgets($node, $locale, $region, $section, $availableWidgets, $widgets, $inheritedWidgets);

        $layouts = $cms->getLayouts();
        $layout = $node->getSectionLayout($region, $section, $this->defaultLayout);

        if (isset($layouts[$layout])) {
            $layout = $layouts[$layout];
        } else {
            $layout = $layouts[$this->defaultLayout];
        }

        $this->setTemplateView('cms/backend/section.content', array(
            'site' => $site,
            'node' => $node,
            'locale' => $locale,
            'region' => $region,
            'section' => $section,
            'layout' => $layout,
            'layouts' => $layouts,
            'widgets' => $widgets,
            'inheritedWidgets' => $inheritedWidgets,
            'actions' => $widgetActionManager->getWidgetActions(),
        ));
    }

    /**
     * Processes the widgets of a section to make them ready for usage
     * @param \ride\library\cms\node\Node $node Instance of the node
     * @param string $region Name of the region
     * @param string $section Name of the section
     * @param array $availableWidgets Array with the id of a widget instance as
     * key and the dependency id as value
     * @param array $widgets Array for the resulting widgets. The key will be
     * the id of the block, the value an array with the widget id as key and the
     * widget instance as value
     * @param array $inheritedWidgets Array with structure of the $widgets var
     * indicating which widgets are inherited, value in the block array is a
     * widget id and not a widget instance
     * @return null
     */
    protected function processSectionWidgets(Node $node, $locale, $region, $section, array $availableWidgets, array &$widgets, array &$inheritedWidgets) {
        $inheritedSectionWidgets = $node->getInheritedWidgets($region, $section);
        $sectionWidgets = $node->getWidgets($region, $section);
        foreach ($sectionWidgets as $block => $blockWidgets) {
            $widgets[$block] = array();
            $inheritedWidgets[$block] = array();

            foreach ($blockWidgets as $widgetId => $widget) {
                if (!$widget || !isset($availableWidgets[$widget])) {
                    continue;
                }

                $widget = clone $availableWidgets[$widget];
                $widget->setIdentifier($widgetId);
                $widget->setProperties($node->getWidgetProperties($widgetId));
                $widget->setLocale($locale);

                if ($widget instanceof AbstractController) {
                    $widget->setConfig($this->config);
                    $widget->setDependencyInjector($this->dependencyInjector);
                }

                $widgets[$block][$widgetId] = $widget;

                if (isset($inheritedSectionWidgets[$block][$widgetId])) {
                    $inheritedWidgets[$block][$widgetId] = $widgetId;
                }
            }
        }
    }

    /**
     * Gets the available regions
     * @param \ride\library\security\SecurityManager $securityManager
     * @param \ride\library\cms\theme\Theme $theme
     * @return array
     */
    protected function getRegions(SecurityManager $securityManager, Theme $theme = null) {
        if (!$theme) {
            return array();
        }

        $regions = $theme->getRegions();
        foreach ($regions as $index => $region) {
            if (!$securityManager->isPermissionGranted('cms.region.' . $theme->getName() . '.' . $region . '.manage')) {
                unset($regions[$index]);
            }
        }

        return $regions;
    }

    /**
     * Gets the available widgets ordered alphabetically
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @return array
     */
    protected function getWidgets(Cms $cms) {
        $translator = $this->getTranslator();
        $orderedWidgets = array();

        $availableWidgets = $cms->getWidgets();
        foreach ($availableWidgets as $index => $widget) {
            $orderedWidgets[$translator->translate('widget.' . $widget->getName())] = $index;
        }

        ksort($orderedWidgets);

        foreach ($orderedWidgets as $index => $widgetIndex) {
            unset($orderedWidgets[$index]);
            $orderedWidgets[$widgetIndex] = $availableWidgets[$widgetIndex];
        }

        return $orderedWidgets;
    }

}
