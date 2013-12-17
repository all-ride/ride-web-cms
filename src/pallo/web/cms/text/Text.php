<?php

namespace pallo\web\cms\text;

/**
 * Interface for text data container
 */
interface Text {

    /**
     * Sets the name of the format
     * @param string $format Name of the format
     * @return null
     */
    public function setFormat($format);

    /**
     * Gets the name of the format
     * @return string
     */
    public function getFormat();

    /**
     * Sets the text
     * @param string $text
     * @return null
     */
    public function setText($text);

    /**
     * Gets the text
     * @return string
     */
    public function getText();

}