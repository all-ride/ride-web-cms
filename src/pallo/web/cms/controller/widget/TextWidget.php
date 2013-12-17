<?php

namespace pallo\web\cms\controller\widget;

use pallo\library\i18n\I18n;
use pallo\library\validation\exception\ValidationException;
use pallo\library\String;

/**
 * Widget to show a static text block
 */
class TextWidget extends AbstractWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'text';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/text.png';

    /**
     * Path to the template of the widget view
     * @var string
     */
    const TEMPLATE = 'cms/widget/text/text';

    /**
     * Parameter for the default format
     * @var string
     */
    const PARAM_DEFAULT_FORMAT = 'cms.text.format';

    /**
     * Parameter for the default IO
     * @var string
     */
    const PARAM_DEFAULT_IO = 'cms.text.io';

    /**
     * Name of the format property
     * @var string
     */
    const PROPERTY_FORMAT = 'format';

    /**
     * Name of the text property
     * @var string
     */
    const PROPERTY_TEXT = 'text';

    /**
     * Name of the I/O property
     * @var string
     */
    const PROPERTY_IO = 'io';

    /**
     * Sets a text view to the response
     * @return null
     */
    public function indexAction() {
        $text = $this->getText();

        $this->setTemplateView(self::TEMPLATE, array(
        	'text' => $text,
        ));

        if ($this->properties->isAutoCache()) {
            $this->properties->setCache(true);
        }
    }

    /**
     * Gets a preview for the properties of this widget
     * @return string
     */
    public function getPropertiesPreview() {
        $preview = $this->getText();

        $previewStripped = trim(strip_tags($preview));
        if (!$previewStripped) {
            $preview = htmlentities($preview);
        } else {
            $preview = new String($previewStripped);
            $preview = $preview->truncate(120);
        }

        return $preview;
    }

    /**
     * Gets the text to display
     * @return string
     */
    protected function getText() {
        $text = $this->getTextIO()->getText($this->properties, $this->locale);
        $format = $this->dependencyInjector->get('pallo\\web\\cms\\text\\format\\TextFormat', $text->getFormat());

        return $format->getHtml($text->getText());
    }

    /**
     * Gets the callback for the properties action
     * @return null|callback Null if the widget does not implement a properties
     * action, a callback for the action otherwise
     */
    public function getPropertiesCallback() {
        return array($this, 'propertiesAction');
    }

    /**
     * Action to handle and show the properties of this widget
     * @param pallo\library\i18n\I18n $i18n
     * @return null
     */
    public function propertiesAction(I18n $i18n) {
        $translator = $this->getTranslator();

        // generate the options
        $formatOptions = array();
        $formats = $this->dependencyInjector->getAll('pallo\\web\\cms\\text\\format\\TextFormat');
        foreach ($formats as $name => $null) {
            $formatOptions[$name] = $translator->translate('text.format.' . $name);
        }
        asort($formatOptions);

        $ioOptions = array();
        $ios = $this->dependencyInjector->getAll('pallo\\web\\cms\\text\\io\\TextIO');
        foreach ($ios as $name => $null) {
            $ioOptions[$name] = $translator->translate('text.io.' . $name);
        }
        asort($ioOptions);

        // get the data
        $text = $this->getTextIO()->getText($this->properties, $this->locale);
        $io = $this->properties->getWidgetProperty(self::PROPERTY_IO);

        if (!$text->getFormat()) {
            $text->setFormat($this->getDefaultTextFormat());
        }
        if (!$io) {
            $io = $this->getDefaultTextIO();
        }

        $data = array(
            self::PROPERTY_FORMAT => $text->getFormat(),
            self::PROPERTY_TEXT => $text->getText(),
            self::PROPERTY_IO => $io,
        );

        if ($this->request->isPost()) {
            $data[self::PROPERTY_FORMAT] = $this->request->getBodyParameter(self::PROPERTY_FORMAT, $data[self::PROPERTY_FORMAT]);
        }

        $textFormat = $this->dependencyInjector->get('pallo\\web\\cms\\text\\format\\TextFormat', $data[self::PROPERTY_FORMAT]);

        // create the form
        $form = $this->createFormBuilder($data);
        $form->setId('form-text');
        $form->addRow('format', 'select', array(
            'label' => $translator->translate('label.text.format'),
            'options' => $formatOptions,
        ));

        $textFormat->processForm($form, $translator, $this->locale);

        $form->addRow('io', 'select', array(
            'label' => $translator->translate('label.text.io'),
            'options' => $ioOptions,
        ));
        $form->addRow('locales-all', 'option', array(
            'label' => '',
            'description' => $translator->translate('label.text.locales.all'),
        ));
        $form->setRequest($this->request);

        // handle form submission
        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                return false;
            }

            if ($this->request->getBodyParameter('action')) {
                try {
                    $form->validate();

                    $data = $form->getData();

                    $this->properties->setWidgetProperty(self::PROPERTY_IO, $data['io']);

                    $text->setFormat($data[self::PROPERTY_FORMAT]);
                    $textFormat->setText($text, $data);

                    $io = $this->getTextIO();
                    if ($data['locales-all']) {
                        // take all locales, not only the locales of the node
                        // so when a locale gets enabled in the future, it
                        // holds the required text
                        $locales = $i18n->getLocaleCodeList();
                        foreach ($locales as $locale) {
                            $io->setText($this->properties, $locale, $text);
                        }
                    } else {
                        $io->setText($this->properties, $this->locale, $text);
                    }

                    return true;
                } catch (ValidationException $e) {
                    $form->setValidationException($e);
                }
            }
        }

        // set view
        $this->setTemplateView('cms/widget/text/properties', array(
            'form' => $form->getView(),
        ));

        return false;
    }

    /**
     * Gets the text IO
     * @return joppa\model\text\io\TextIO
     */
    protected function getTextIO() {
        $io = $this->getDefaultTextIO();
        $io = $this->properties->getWidgetProperty(self::PROPERTY_IO, $io);

        if (!$io) {
            $io = null;
        }

        return $this->dependencyInjector->get('pallo\\web\\cms\\text\\io\\TextIO', $io);
    }


    /**
     * Gets the name of the default text format
     * @return string
     */
    protected function getDefaultTextFormat() {
        return $this->config->get(self::PARAM_DEFAULT_FORMAT, 'html');
    }

    /**
     * Gets the name of the default text IO
     * @return string
     */
    protected function getDefaultTextIO() {
        return $this->config->get(self::PARAM_DEFAULT_IO, 'properties');
    }

}