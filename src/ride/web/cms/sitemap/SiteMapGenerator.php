<?php

namespace ride\web\cms\sitemap;

use ride\library\cms\sitemap\SiteMapGenerator as LibSiteMapGenerator;
use ride\library\cms\widget\Widget;
use ride\library\cms\Cms;
use ride\library\config\Config;
use ride\library\dependency\DependencyInjector;
use ride\library\http\Request;

use ride\web\base\controller\AbstractController;

/**
 * Generator of the site map files
 */
class SiteMapGenerator extends LibSiteMapGenerator {

    /**
     * Constructs a new site map generator
     * @param \ride\library\cms\Cms $cms
     * @return null
     */
    public function __construct(Cms $cms, Config $config, DependencyInjector $dependencyInjector, Request $request) {
        parent::__construct($cms);

        $this->config = $config;
        $this->dependencyInjector = $dependencyInjector;
        $this->request = $request;
    }

    /**
     * Hook to perform extra processing on a widget
     * @param \ride\library\cms\widget\Widget $widget
     * @return null
     */
    protected function prepareWidget(Widget $widget) {
        if (!$widget instanceof AbstractController) {
            return;
        }

        $widget->setConfig($this->config);
        $widget->setDependencyInjector($this->dependencyInjector);
        $widget->setRequest($this->request);
    }

}
