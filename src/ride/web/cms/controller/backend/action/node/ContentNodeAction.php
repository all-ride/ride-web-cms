<?php

namespace ride\web\cms\controller\backend\action\node;

use ride\library\cms\layout\Layout;
use ride\library\cms\layout\LayoutModel;
use ride\library\cms\node\Node;
use ride\library\cms\node\NodeProperty;
use ride\library\cms\theme\Theme;

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

        $cms->setLastAction(self::NAME);

        $theme = $cms->getTheme($node->getTheme());

        $form = $this->buildRegionForm($node, $theme);
        if ($form->isSubmitted()) {
            $data = $form->getData();

            $region = $data['region'];
        } else {
            $region = $cms->getLastRegion();
        }

        if (!$region || ($region && !$theme->hasRegion($region))) {
            $regions = $theme->getRegions();
            $region = array_shift($regions);
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

        $regions = $theme->getRegions();
        $availableWidgets = $cms->getWidgets();

        $cms->setLastAction(self::NAME);
        $cms->setLastRegion($region);

        $form = $this->buildRegionForm($node, $theme, $region);

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

        $node->setSectionLayout($region, $section, $layout);

        $cms->saveNode($node, 'Set layout of section ' . $section . ' from region ' . $region . ' on node ' . $node->getName() . ' to ' . $layout);

        $this->setSectionView($cms, $widgetActionManager, $site, $node, $locale, $region, $section);
    }


    /**
     * Action to dispatch to the properties of a widget
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
     * Action to reorder the sections of a region
     * @param \ride\web\cms\Cms $cms Facade to the CMS
     * @param string $locale Code of the locale
     * @param string $site Id of the site
     * @param string $revision Name of the revision
     * @param string $node Id of the node
     * @param string $region Name of the region
     * @return null
     */
    public function sectionOrderAction(Cms $cms, $locale, $site, $revision, $node, $region) {
        $theme = null;
        if (!$cms->resolveNode($site, $revision, $node) || !$cms->resolveRegion($node, $locale, $region, $theme)) {
            return;
        }

        $order = $this->request->getBodyParameter('order', array());

        $node->orderSections($region, $order);

        $cms->saveNode($node, 'Reordered sections from region ' . $region . ' on node ' . $node->getName());
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
    protected function buildRegionForm(Node $node, Theme $theme = null, $region = null) {
        if ($theme) {
            $regions = $theme->getRegions();
        } else {
            $regions = array();
        }

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

}
