<?php

namespace ride\web\cms\controller\widget;

/**
 * Interface for a widget with style support
 */
interface StyleWidget {

    /**
     * Gets the options for the styles
     * @return array Array with the name of the option as key and the
     * translation key as value
     */
    public function getWidgetStyleOptions();

}
