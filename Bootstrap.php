<?php
/**
 * Less Bootstrap file
 * ===
 *
 * Load the LessCompiler Component and start the autocompiler
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2014 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 */
class Less_Bootstrap extends Zend_Application_Module_Bootstrap
{
    protected function _initLibraryAutoloader ()
    {
        define('LESS_MODULE_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
        define('LESS_MODULE_VENDOR_PATH', LESS_MODULE_PATH . 'vendors' . DIRECTORY_SEPARATOR);

        return $this->getResourceLoader()->addResourceType('library', 'library', 'Library_');
    }

    protected function _initCompileLessFiles()
    {
        $compilerInstance = new Less_Library_LessCompilerComponent($this->getOptions());
        $compilerInstance->generateCss();
    }
}