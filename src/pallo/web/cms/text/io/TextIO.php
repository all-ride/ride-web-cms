<?php

namespace pallo\web\cms\text\io;

use pallo\library\widget\WidgetProperties;

use pallo\web\cms\text\Text;

/**
 * Interface for input/output of the text widget
 */
interface TextIO {

    /**
     * Stores the text in the data source
     * @param pallo\library\widget\WidgetProperties $widgetProperties Instance
     * of the widget properties
     * @param string $locale Code of the current locale
     * @param pallo\web\cms\text\Text $text Instance of the text
     * @return null
     */
    public function setText(WidgetProperties $widgetProperties, $locale, Text $text);

    /**
     * Gets the text from the data source
     * @param pallo\library\widget\WidgetProperties $widgetProperties Instance
     * of the widget properties
     * @param string $locale Code of the current locale
     * @return pallo\web\cms\text\Text Instance of the text
     */
    public function getText(WidgetProperties $widgetProperties, $locale);

}