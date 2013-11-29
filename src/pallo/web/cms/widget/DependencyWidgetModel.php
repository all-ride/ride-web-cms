<?php

namespace pallo\web\cms\widget;

use pallo\library\cms\widget\WidgetModel;
use pallo\library\dependency\DependencyInjector;

/**
 * Model of the available widgets through dependency injection
 */
class DependencyWidgetModel implements WidgetModel {

    /**
     * Instance of the dependency injector
     * @var pallo\library\dependency\DependencyInjector
     */
    protected $dependencyInjector;

    /**
     * Constructs a new widget model
     * @param pallo\library\dependency\DependencyInjector $dependencyInjector
     * @return null
     */
    public function __construct(DependencyInjector $dependencyInjector) {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Gets the instance of a widget
     * @param string $widget Machine name of the widget
     * @return pallo\library\cms\widget\Widget
     */
    public function getWidget($widget) {
        return $this->dependencyInjector->get('pallo\\library\\cms\\widget\\Widget', $widget);
    }

    /**
     * Gets the available widgets
     * @return array Array with the machine name of the widget as key and an
     * instance of Widget as value
     */
    public function getWidgets() {
        return $this->dependencyInjector->getAll('pallo\\library\\cms\\widget\\Widget');
    }

}