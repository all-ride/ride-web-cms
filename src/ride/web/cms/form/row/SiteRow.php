<?php

namespace ride\web\cms\form\row;

use ride\library\form\exception\FormException;
use ride\library\form\row\OptionRow;
use ride\library\validation\factory\ValidationFactory;

use ride\web\cms\Cms;

/**
 * Cms site row
 */
class SiteRow extends OptionRow {

    /**
     * Name of the locale option
     * @var string
     */
    const OPTION_LOCALE = 'locale';

    /**
     * Instance of the CMS
     * @var \ride\web\cms\Cms
     */
    protected $cms;

    /**
     * Flag to see if this row can have multiple values
     * @var boolean
     */
    private $isMultiple = false;

    /**
     * Sets the CMS instance
     * @param \ride\web\cms\Cms $cms
     * @return null
     */
    public function setCms(Cms $cms) {
        $this->cms = $cms;
    }

    public function setIsMultiple($isMultiple) {
        $this->isMultiple = $isMultiple;
    }

    /**
     * Sets the data to this row
     * @param mixed $data
     * @return null
     */
    public function setData($data) {
        if ($this->isMultiple && !is_array($data)) {
            $tmp = explode(',', $data);

            $data = array();
            foreach ($tmp as $site) {
                $data[$site] = $site;
            }
        }

        parent::setData($data);
    }

    /**
     * Gets the data of this row
     * @return mixed
     */
    public function getData() {
        if ($this->isMultiple && is_array($this->data)) {
            return implode(',', $this->data);
        }

        return $this->data;
    }

    /**
     * Performs necessairy build actions for this row
     * @param string $namePrefix Prefix for the row name
     * @param string $idPrefix Prefix for the field id
     * @param \ride\library\validation\factory\ValidationFactory $validationFactory
     * @return null
     */
    public function buildRow($namePrefix, $idPrefix, ValidationFactory $validationFactory) {
        $this->setSiteOptions();

        parent::buildRow($namePrefix, $idPrefix, $validationFactory);
    }

    /**
     * Sets the necessairy options for this row to work
     * @return null
     */
    protected function setSiteOptions() {
        $locale = $this->getOption(self::OPTION_LOCALE);
        $options = array();

        $sites = $this->cms->getSites();
        foreach ($sites as $site) {
            $options[$site->getId()] = $site->getName($locale);
        }

        $this->setOption(self::OPTION_OPTIONS, $options);

        $options = $this->getOptions();
        if (!array_key_exists(self::OPTION_MULTIPLE, $options)) {
            $this->setOption(self::OPTION_MULTIPLE, $this->isMultiple);
        }
    }

}
