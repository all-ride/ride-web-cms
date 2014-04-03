<?php

namespace ride\web\cms\controller\widget;

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
     * Unique identifier of this widget
     * @var string
     */
    protected $id;

    /**
     * Properties of this widget
     * @var \ride\library\cms\widget\WidgetProperties
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
        if (defined('static::TEMPLATE')) {
            return array(static::TEMPLATE);
        }

        return null;
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
     * @return \ride\web\base\view\BaseTemplateView
     */
    protected function setTemplateView($resource, array $variables = null) {
        $templateFacade = $this->dependencyInjector->get('ride\\library\\template\\TemplateFacade');

        $template = $templateFacade->createTemplate($resource, $variables);

        $view = new TemplateView($template);
        $view->setTemplateFacade($templateFacade);

        $this->response->setView($view);

        return $view;
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
    protected function getUrl($id, array $variables = array()) {
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
