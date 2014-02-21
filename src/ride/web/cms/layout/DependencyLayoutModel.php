<?php

namespace ride\web\cms\layout;

use ride\library\cms\layout\LayoutModel;
use ride\library\dependency\DependencyInjector;

/**
 * Model of the available widgets through dependency injection
 */
class DependencyLayoutModel implements LayoutModel {

    /**
     * Instance of the dependency injector
     * @var ride\library\dependency\DependencyInjector
     */
    protected $dependencyInjector;

    /**
     * Constructs a new layout model
     * @param ride\library\dependency\DependencyInjector $dependencyInjector
     * @return null
     */
    public function __construct(DependencyInjector $dependencyInjector) {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Gets the a specific layout
     * @param string $layout Machine name of the layout
     * @return Layout
     */
    public function getLayout($layout) {
        return $this->dependencyInjector->get('ride\\library\\cms\\layout\\Layout', $layout);
    }

    /**
     * Gets the available layouts
     * @return array Array with the machine name of the layout as key and an
     * instance of Layout as value
     */
    public function getLayouts() {
        return $this->dependencyInjector->getAll('ride\\library\\cms\\layout\\Layout');
    }

}