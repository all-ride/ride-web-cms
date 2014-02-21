<?php

namespace ride\web\cms\widget;

use ride\library\cms\widget\WidgetModel;
use ride\library\dependency\DependencyInjector;

/**
 * Model of the available widgets through dependency injection
 */
class DependencyWidgetModel implements WidgetModel {

    /**
     * Instance of the dependency injector
     * @var ride\library\dependency\DependencyInjector
     */
    protected $dependencyInjector;

    /**
     * Constructs a new widget model
     * @param ride\library\dependency\DependencyInjector $dependencyInjector
     * @return null
     */
    public function __construct(DependencyInjector $dependencyInjector) {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Gets the instance of a widget
     * @param string $widget Machine name of the widget
     * @return ride\library\cms\widget\Widget
     */
    public function getWidget($widget) {
        return $this->dependencyInjector->get('ride\\library\\cms\\widget\\Widget', $widget);
    }

    /**
     * Gets the available widgets
     * @return array Array with the machine name of the widget as key and an
     * instance of Widget as value
     */
    public function getWidgets() {
        return $this->dependencyInjector->getAll('ride\\library\\cms\\widget\\Widget');
    }

}