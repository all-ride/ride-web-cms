<?php

namespace ride\web\cms\theme;

use ride\library\cms\theme\Theme;
use ride\library\StringHelper;

/**
 * Generic theme
 */
class GenericTheme implements Theme {

    /**
     * Machine name of the theme
     * @var string
     */
    protected $name;

    /**
     * Display name of the theme
     * @var string
     */
    protected $displayName;

    /**
     * Machine name of the parent theme
     * @var string
     */
    protected $parent;

    /**
     * Available template engines
     * @var array
     */
    protected $engines;

    /**
     * Available regions
     * @var array
     */
    protected $regions;

    /**
     * Constructs a new theme
     * @param string $name Machine name of the theme
     * @param string $displayName Display name of the theme
     * @param array $engines Array with the engine name as key and as value
     * @param array $regions Array with the region name as key and as value
     * @param string $parent Machine name of the parent
     * @return null
     */
    public function __construct($name, $displayName, $engines, $regions, $parent = null) {
        $this->name = StringHelper::safeString($name);
        $this->displayName = $displayName;
        $this->engines = $engines;
        $this->regions = $regions;
        $this->parent = $parent;
    }

    /**
     * Gets the machine name of the theme
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Gets the display name of this theme
     * @return string
     */
    public function getDisplayName() {
        return $this->displayName;
    }

    /**
     * Gets the parent theme
     * @return string Machine name of the parent theme
    */
    public function getParent() {
        return $this->parent;
    }

    /**
     * Gets the machine name(s) of the available template engines
     * @return string|array
    */
    public function getEngines() {
        return $this->engines;
    }

    /**
     * Checks if a region exists in this layout
     * @return boolean
     */
    public function hasRegion($region) {
        return isset($this->regions[$region]);
    }

    /**
     * Gets the regions for this theme
     * @return array Array with the region name as key and as value
    */
    public function getRegions() {
        return $this->regions;
    }

}
