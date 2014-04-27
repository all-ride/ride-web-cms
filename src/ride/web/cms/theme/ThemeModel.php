<?php

namespace ride\web\cms\theme;

use ride\library\cms\layout\LayoutModel;
use ride\library\cms\theme\TemplateThemeModel;
use ride\library\config\Config;
use ride\library\system\file\File;
use ride\library\template\TemplateFacade;
use ride\library\template\exception\ResourceNotFoundException;
use ride\library\template\exception\ThemeNotFoundException;
use ride\library\template\theme\ThemeModel as LibraryThemeModel;

/**
 * CMS theme model extended with themes from the configuration
 */
class ThemeModel extends TemplateThemeModel {

    /**
     * Instance of the configuration
     * @var \ride\library\config\Config;
     */
    protected $config;

    /**
     * Instance of the layout model
     * @var \ride\library\cms\layout\LayoutModel;
     */
    protected $layoutModel;

    /**
     * Instance of the template facade
     * @var \ride\library\template\TemplateFacade;
     */
    protected $templateFacade;

    /**
     * Directory to write dummy templates
     * @var \ride\library\system\file\File
     */
    protected $directory;

    /**
     * Available themes
     * @var array
     */
    protected $themes;

    /**
     * Constructs a new theme model
     * @param \ride\library\config\Config $config Instance of the configuration
     * @param \ride\library\template\theme\ThemeModel $model Instance of the
     * template theme model
     * @param \ride\library\cms\layout\LayoutModel $layoutModel Instance
     * of the layout model
     * @param \ride\library\system\file\File $directory Directory to write
     * dummy templates
     * @return null
     */
    public function __construct(Config $config, LibraryThemeModel $model, LayoutModel $layoutModel, File $directory) {
        parent::__construct($model);

        $this->config = $config;
        $this->layoutModel = $layoutModel;
        $this->templateFacade = null;
        $this->directory = $directory;
        $this->themes = null;
    }

    /**
     * Sets the instance of the template facade to generate templates if
     * necessairy
     * @param \ride\library\template\TemplateFacade $templateFacade Instance of
     * the template facade
     * @return null
     */
    public function setTemplateFacade(TemplateFacade $templateFacade) {
        $this->templateFacade = $templateFacade;
    }

    /**
     * Gets a theme
     * @param string $name Machine name of the theme
     * @return Theme
     * @throws \ride\library\template\exception\ThemeNotFoundException
     */
    public function getTheme($name) {
        $themes = $this->getThemes();
        if (!isset($themes[$name])) {
            throw new ThemeNotFoundException($name);
        }

        return $themes[$name];
    }

    /**
     * Gets the available themes
     * @return array Array with the machine name of the theme as key and an
     * instance of Theme as value
     */
    public function getThemes() {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $this->themes = parent::getThemes();

        $themes = $this->config->get('theme');
        if (!is_array($themes)) {
            return $this->themes;
        }

        foreach ($themes as $name => $theme) {
            if (isset($theme['name'])) {
                $displayName = $theme['name'];
            } else {
                $displayName = $name;
            }

            if (isset($theme['engine'])) {
                if (is_array($theme['engine'])) {
                    $engines = $theme['engine'];
                } else {
                    $engines = array($theme['engine']);
                }
            } else {
                $engines = array();
            }

            if (isset($theme['region'])) {
                $regions = $theme['region'];
            } else {
                $regions = array();
            }

            if (isset($theme['parent'])) {
                $parent = $theme['parent'];
            } else {
                $parent = null;
            }

            $this->themes[$name] = new GenericTheme($name, $displayName, $engines, $regions, $parent);
        }

        return $this->themes;
    }

    /**
     * Writes a theme to the configuration
     * @param GenericTheme $theme
     * @return null
     */
    public function setTheme(GenericTheme $theme) {
        $data = array(
            'name' => $theme->getDisplayName(),
            'parent' => $theme->getParent(),
        	'engine' => $theme->getEngines(),
            'region' => $theme->getRegions(),
        );

        $this->config->set('theme.' . $theme->getName(), $data);
        $this->themes[$theme->getName()] = $theme;

        $this->createResources($theme);
    }

    /**
     * Creates dummy template resources for a theme if needed
     * @param GenericTheme $theme
     * @return null
     */
    protected function createResources(GenericTheme $theme) {
        if ($theme->getParent() || !$this->templateFacade) {
            return;
        }

        $resources = array(
            'cms/frontend/index',
        );

        $layouts = $this->layoutModel->getLayouts();
        foreach ($layouts as $layout) {
            $resources[] = 'cms/frontend/layout.' . $layout->getName();
        }

        $engines = $theme->getEngines();
        $theme = $theme->getName();

        foreach ($resources as $resource) {
            foreach ($engines as $engine) {
                $extension = $this->templateFacade->getEngineModel()->getEngine($engine)->getExtension();

                try {
                    $template = $this->templateFacade->createTemplate($resource, null, $theme, $engine);
                    $file = $this->templateFacade->getFile($template);
                } catch (ResourceNotFoundException $exception) {
                    $file = $this->directory->getChild('view/' . $engine . '/' . $theme . '/' . $resource . '.' . $extension);
                    $file->write('');
                }
            }
        }
    }

    /**
     * Removes a theme from the configuration
     * @param GenericTheme $theme
     * @return null
     */
    public function removeTheme(GenericTheme $theme) {
        $engines = $theme->getEngines();
        $theme = $theme->getName();

        $this->config->set('theme.' . $theme, null);

        foreach ($engines as $engine) {
            $directory = $this->directory->getChild('view/' . $engine . '/' . $theme);
            if ($directory->exists()) {
                $directory->delete();
            }
        }
    }

}
