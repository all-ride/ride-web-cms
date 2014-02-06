<?php

namespace pallo\web\cms\content\mapper\io;

use pallo\library\cms\content\mapper\io\ContentMapperIO;
use pallo\library\dependency\DependencyInjector;

use \Exception;

/**
 * Implementation to load content mappers from the dependency injector
 */
class DependencyContentMapperIO implements ContentMapperIO {

    /**
     * Constructs a new dependency content mapper io
     * @param pallo\library\dependency\DependencyInjector $dependencyInjector
     * @return null
     */
    public function __construct(DependencyInjector $dependencyInjector) {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Gets a content mapper
     * @return pallo\library\cms\content\mapper\ContentMapper|null
     */
    public function getContentMapper($type) {
        try {
            return $this->dependencyInjector->get('pallo\\library\\cms\\content\\mapper\\ContentMapper', $type);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * Gets the available mappers
     * @return array Array with ContentMapper objects
     * @see pallo\library\cms\content\mapper\ContentMapper
     */
    public function getContentMappers() {
        return $this->dependencyInjector->getAll('pallo\\library\\cms\\content\\mapper\\ContentMapper');
    }

}