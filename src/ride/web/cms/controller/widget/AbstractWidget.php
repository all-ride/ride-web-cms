<?php

namespace ride\web\cms\controller\widget;

use ride\library\cms\node\NodeModel;
use ride\library\cms\widget\Widget;
use ride\library\system\file\File;
use ride\library\widget\WidgetProperties;

use ride\web\base\controller\AbstractController;
use ride\web\mvc\view\TemplateView;

/**
 * Abstract implementation for a widget
 */
class AbstractWidget extends AbstractController implements Widget {

    /**
     * Path to the default icon of the widget
     * @var string
     */
    const ICON =  'img/cms/widget.png';

    /**
     * Name of the title property
     * @var string
     */
    const PROPERTY_TITLE = 'title';

    /**
     * Name of the template property
     * @var string
     */
    const PROPERTY_TEMPLATE = 'template';

    /**
     * Unique identifier of this widget
     * @var string
     */
    protected $id;

    /**
     * Properties of this widget
     * @var \ride\library\cms\widget\NodeWidgetProperties
     */
    protected $properties;

    /**
     * Code of the locale for the widget request
     * @var string
     */
    protected $locale;

    /**
     * Name of the region for the widget request
     * @var string
     */
    protected $region;

    /**
     * Name of the section for the widget request
     * @var string
     */
    protected $section;

    /**
     * Name of the block for the widget request
     * @var string
     */
    protected $block;

    /**
     * Context of the node
     * @var array
     */
    protected $context;

    /**
     * Flag to set whether to display this widget as page
     * @var boolean
     */
    private $isContent = false;

    /**
     * Flag to set whether this is the only widget to be displayed in the
     * containing region
     * @var boolean
     */
    private $isRegion = false;

    /**
     * Flag to set whether this is the only widget to be displayed in the
     * containing section
     * @var boolean
     */
    private $isSection = false;

    /**
     * Flag to set whether this is the only widget to be displayed in the
     * containing block
     * @var boolean
     */
    private $isBlock = false;

    /**
     * Flag to set whether this widget displays user specific content
     * @var boolean
     */
    private $containsUserContent = false;

    /**
     * Gets the machine name of the widget
     * @return string
     */
    public function getName() {
        return static::NAME;
    }

    /**
     * Gets the path to the icon of the widget
     * @return string|boolean
     */
    public function getIcon() {
        return static::ICON;
    }

    /**
     * Sets the unique identifier of the widget
     * @param string $identifier Unique identifier
     * @return null
     */
    public function setIdentifier($identifier) {
        $this->id = $identifier;
    }

    /**
     * Sets the code of the locale for the widget request
     * @param string $locale Code of the locale
     * @return null
     */
    public function setLocale($locale) {
        $this->locale = $locale;
    }

    /**
     * Gets the callback for the widget action
     * @return callback Callback for the action
     */
    public function getCallback() {
        return array($this, 'indexAction');
    }

    /**
     * Sets the region for the widget request
     * @param string $region Name of the region
     * @return null
     */
    public function setRegion($region) {
        $this->region = $region;
    }

    /**
     * Sets the section for the widget request
     * @param string $region Name of the section
     * @return null
     */
    public function setSection($section) {
        $this->section = $section;
    }

    /**
     * Sets the block for the widget request
     * @param string $block Name of the block
     * @return null
     */
    public function setBlock($block) {
        $this->block = $block;
    }

    /**
     * Gets the additional sub routes of this widget
     * @return array|null Array with Route objects
     * @see ride\library\router\Route
     */
    public function getRoutes() {
        return null;
    }

    /**
     * Gets the templates used by this widget
     * @return array Array with the resource names of the templates
     */
    public function getTemplates() {
        $templates = array();

        $templateProperties = $this->properties->getWidgetProperties(self::PROPERTY_TEMPLATE);
        foreach ($templateProperties as $template) {
            $templates[$template] = $template;
        }

        return $templates;
    }

    /**
     * Gets the available templates for the provided namespace
     * @param string $namespace Relative path inside the theme directory of the
     * templates
     * @param string $widget Name of the widget, if set, needs to match in the
     * template meta
     * @param string $action Name of the action, if set, needs to match in the
     * template meta
     * @return array Array with the relative path to the template resource as
     * key and the name of the template as value
     */
    public function getAvailableTemplates($namespace, $widget = null, $action = null) {
        $translator = $this->getTranslator();

        $themeModel = $this->dependencyInjector->get('ride\\library\\cms\\theme\\ThemeModel');

        $templateFacade = $this->dependencyInjector->get('ride\\library\\template\\TemplateFacade');
        $templateFacade->setThemeModel($themeModel);

        $theme = $this->properties->getNode()->getTheme();
        $engine = null;
        $files = $templateFacade->getFiles($namespace, $theme, $engine);
        foreach ($files as $path => $name) {
            if (strpos($name, 'properties') === 0) {
                unset($files[$path]);

                continue;
            }

            $template = $templateFacade->createTemplate($path, null, $theme);
            $meta = $templateFacade->getTemplateMeta($template);

            if ($widget && (!isset($meta['widget']) || $meta['widget'] != $widget)) {
                unset($files[$path]);

                continue;
            }
            if ($action && (!isset($meta['action']) || $meta['action'] != $action)) {
                unset($files[$path]);

                continue;
            }

            if (isset($meta['translation'])) {
                $files[$path] = $translator->translate($meta['translation']);
            } elseif (isset($meta['name'])) {
                $files[$path] = $meta['name'];
            }
        }

        asort($files);

        return $files;
    }

    /**
     * Gets a the path to a template resource from the properties
     * @param string $default Fallback template when no property set
     * @param string $context Name of the template context
     * @return string Path to the requested template resource
     */
    public function getTemplate($default = null, $context = null) {
        $templateKey = self::PROPERTY_TEMPLATE;
        if ($context) {
            $templateKey .= '.' . $context;
        }

        return $this->properties->getWidgetProperty($templateKey, $default);
    }

    /**
     * Sets the path for a template resource to the properties
     * @param string $template Path to the template resource
     * @param string $context Name of the template context
     * @return null
     */
    public function setTemplate($template, $context = null) {
        $templateKey = self::PROPERTY_TEMPLATE;
        if ($context) {
            $templateKey .= '.' . $context;
        }

        $this->properties->setWidgetProperty($templateKey, $template);
    }

    /**
     * Gets a list of frontend nodes
     * @param \ride\library\cms\node\NodeModel $nodeModel Instance of the node
     * model
     * @param boolean $includeRootNode Set to false to omit the root node
     * @return array Array with the id of node as key and the localized name of
     * the node as value
     */
    public function getNodeList(NodeModel $nodeModel, $includeRootNode = true) {
        $node = $this->properties->getNode();

        $rootNodeId = $node->getRootNodeId();
        $rootNode = $nodeModel->getNode($rootNodeId, $node->getRevision(), $rootNodeId, null, true);

        $nodeList = $nodeModel->getListFromNodes(array($rootNode), $this->locale, true);

        if ($includeRootNode) {
            $nodeList = array($rootNode->getId() => '/' . $rootNode->getName($this->locale)) + $nodeList;
        }

        return array('' => '---') + $nodeList;
    }

    /**
     * Sets the properties of the widget instance
     * @param \ride\library\widget\WidgetProperties $properties Properties for
     * the widget instance
     * @return null
     */
    public function setProperties(WidgetProperties $properties) {
        $this->properties = $properties;
    }

    /**
     * Gets the callback for the properties action
     * @return null|callback Null if the widget does not implement a properties
     * action, a callback for the action otherwise
     */
    public function getPropertiesCallback() {
        if (method_exists($this, 'propertiesAction')) {
            return array($this, 'propertiesAction');
        }

        return null;
    }

    /**
     * Gets a human preview of the set properties
     * @return string
     */
    public function getPropertiesPreview() {
        return null;
    }

    /**
     * Get the breadcrumbs of the page
     * @return array Array with the URL as key and the label as value
     */
    public function getBreadcrumbs() {
        return $this->context['breadcrumbs'];
    }

    /**
     * Add a breadcrumb to the page
     * @param string $url URL for the breadcrumb
     * @param string $label Label for the breadcrumb
     * @return null
     */
    protected function addBreadcrumb($url, $label) {
        $this->context['breadcrumbs'][$url] = $label;
    }

    /**
     * Sets the title of the page
     * @param string $title
     * @return null
     */
    protected function setPageTitle($title) {
        $this->context['title']['node'] = $title;
    }

    /**
     * Gets the content facade
     * @return \ride\library\cms\content\ContentFacade
     */
    public function getContentFacade() {
        return $this->dependencyInjector->get('ride\\library\\cms\\content\\ContentFacade');
    }

    /**
     * Gets the content mapper for the provided content type
     * @param string $type Name of the content type
     * @return \ride\library\cms\content\mapper\ContentMapper
     */
    public function getContentMapper($type) {
        return $this->getContentFacade()->getContentMapper($type);
    }

    /**
     * Gets whether this widget caches when auto cache is enabled
     * @return boolean
     */
    public function isAutoCache() {
        return false;
    }

    /**
     * Sets whether to display this widget as page
     * @param boolean $isContent True to only display this widget
     * @return null
     */
    protected function setIsContent($isContent) {
        $this->isContent = $isContent;
    }

    /**
     * Gets whether to display this widget as page
     * @return boolean True to only display this widget
     */
    public function isContent() {
        return $this->isContent;
    }

    /**
     * Sets if this is the only widget to be displayed in the containing region
     * @param boolean $isRegion True to only display this widget in the region
     * @return null
     */
    protected function setIsRegion($isRegion) {
        $this->isRegion = $isRegion;
    }

    /**
     * Gets whether this is the only widget to be displayed in the containing region
     * @return boolean True to only display this widget in the region
     */
    public function isRegion() {
        return $this->isRegion;
    }

    /**
     * Sets if this is the only widget to be displayed in the containing section
     * @param boolean $isSection True to only display this widget in the section
     * @return null
     */
    protected function setIsSection($isSection) {
        $this->isSection = $isSection;
    }

    /**
     * Gets whether this is the only widget to be displayed in the containing section
     * @return boolean True to only display this widget in the section
     */
    public function isSection() {
        return $this->isSection;
    }

    /**
     * Sets if this is the only widget to be displayed in the containing block
     * @param boolean $isBlock True to only display this widget in the block
     * @return null
     */
    protected function setIsBlock($isBlock) {
        $this->isBlock = $isBlock;
    }

    /**
     * Gets whether this is the only widget to be displayed in the containing block
     * @return boolean True to only display this widget in the block
     */
    public function isBlock() {
        return $this->isBlock;
    }

    /**
     * Sets whether this widget contains user content
     * @param boolean $containsUserContent
     * @return null
     */
    protected function setContainsUserContent($containsUserContent) {
        $this->containsUserContent = $containsUserContent;
    }

    /**
     * Gets whether this widget contains user content
     * @return boolean
     */
    public function containsUserContent() {
        return $this->containsUserContent;
    }

    /**
     * Sets the context of the node
     * @param string|array $context Name of the context variable or an array
     * of key-value pairs
     * @param mixed $value Context value
     * @return null
     */
    public function setContext($context, $value = null) {
        if (is_array($context)) {
            foreach ($context as $key => $value) {
                $this->setContext($key, $value);
            }
        } elseif ($value !== null) {
            $this->context[$context] = $value;
        } elseif (isset($this->context[$context])) {
            unset($this->context[$context]);
        }
    }

    /**
     * Gets the context of the node
     * @param string $name Name of the context variable
     * @param mixed $default Default value for when the variable is not set
     * @return mixed Full context if no arguments provided, value of the
     * variable if set in the context, provided default value otherwise
     */
    public function getContext($name = null, $default = null) {
        if ($name === null) {
            return $this->context;
        } elseif (isset($this->context[$name])) {
            return $this->context[$name];
        } else {
            return $default;
        }
    }

    /**
     * Sets a template view to the response
     * @param string $resource Resource to the template
     * @param array $variables Variables for the template
     * @param string $id Id of the template view in the dependency injector
     * @return \ride\web\mvc\view\TemplateView
     */
    protected function setTemplateView($resource, array $variables = null, $id = null) {
        if ($id === null) {
            $id = 'widget';
        }

        return parent::setTemplateView($resource, $variables, $id);
    }

    /**
     * Sets a download view for the provided file to the response
     * @param \ride\library\system\file\File $file File which needs to be
     * offered for download
     * @param string $name Name for the download
     * @param boolean $cleanUp Set to true to register an event to clean up the
     * file after the response has been sent
     * @return null
     */
    protected function setDownloadView(File $file, $name = null, $cleanUp = false) {
        parent::setDownloadView($file, $name, $cleanUp);

        $this->setIsContent(true);
    }

    /**
     * Gets the URL of the provided route
     * @param string $routeId Id of the route
     * @param array $arguments Path arguments for the route
     * @return string
     * @throws \ride\library\router\exception\RouterException If the route is
     * not found
     */
    public function getUrl($id, array $variables = array()) {
        $routes = $this->getRoutes();
        if ($routes) {
            foreach ($routes as $route) {
                if ($route->getId() != $id) {
                    continue;
                }

                $node = $this->properties->getNode();
                $url = rtrim($this->request->getBaseScript() . $node->getRoute($this->locale), '/');
                $url = $route->getUrl($url, $variables);

                return $url;
            }
        }

        return parent::getUrl($id, $variables);
    }

}
