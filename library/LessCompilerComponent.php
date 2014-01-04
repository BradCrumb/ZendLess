<?php
/**
 * LessCompiler Component
 * ===
 *
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2014 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 * @todo add php-functions to the lessc configuration
 */
class Less_Library_LessCompilerComponent
{
    public $settings = array(
        // Where to look for Less files (Default: APPLICATION_PATH/less)
        'sourceFolder'      => null,
        // Where to put the generated css (Default: DOCUMENT_ROOT/css)
        'targetFolder'      => null,
        // lessphp compatible formatter
        'formatter'         => 'compressed',
        // Preserve comments or remove them
        'preserveComments'  => null,
        // Pass variables from php to Less
        'variables'         => array(),
        // Always compile the Less files
        'forceCompiling'    => false,
        // Check if compilation is necessary, this ignores the debug settings
        'autoRun'           => false,
    );
/**
* Minimum required PHP version
*
* @var string
*/
    protected static $_minVersionPHP = '5.3';

/**
* Minimum required Lessc.php version
*
* @var string
*/
    protected static $_minVersionLessc = '0.3.9';

/**
 * Contains the indexed folders consisting of less-files
 *
 * @var array
 */
    protected $_lessFolders;

/**
 * Contains the folders with processed css files
 *
 * @var array
 */
    protected $_cssFolders;

/**
 * @var Zend_Cache
 */
    protected $_cache;

/**
 * Status whether component is enabled or disabled
 *
 * @var boolean
 */
    public $enabled = true;

/**
 * Class constructor
 *
 * @param array $settings
 */
    public function __construct(array $settings = array())
    {
        $this->_checkVersion();

        $this->settings = array_merge($this->settings, $settings);

        /**
         * Disable the module so no unnecessary compiling will be done unless
         * - you're not in a production environment
         * - forceLessToCompile has been set in the request parameters
         * - the autoRun configuration parameter has set to true
         */
        if ((!defined('APPLICATION_ENV') || 'production' == APPLICATION_ENV) &&
            !isset($_GET['forceLessToCompile']) &&
            false === $this->settings['autoRun']) {
                $this->enabled = false;
                return false;
        }

        /**
         * When you are in a production environment, you always have to run with forceLessToCompile=true
         * or set the Autorun configuration setting to true
         */
        if (isset($_GET['forceLessToCompile'])) {
            $this->settings['forceCompiling'] = true;
        }

        $this->_setCache();

        $this->_setFolders();
    }

/**
* Checks the versions of PHP and lessphp
*
* @throws Zend_Exception If one of the required versions is not available
*
* @return void
*/
    protected function _checkVersion()
    {
        if (PHP_VERSION < self::$_minVersionPHP) {
            throw new Zend_Exception(
                __('The LessCompiler plugin requires PHP version %s or higher!', self::$_minVersionPHP)
            );
        }

        if (Less_Library_LessCompiler::$VERSION < self::$_minVersionLessc) {
            throw new Zend_Exception(
                __('The LessCompiler plugin requires lessc version %s or higher!', self::$_minVersionLessc)
            );
        }
    }

/**
 * Sets the cache for back- and frontend
 * Settings from application.ini are merged into the default settings
 *
 * @example LessCompiler.cache.frontendOptions.lifetime = 3600
 *
 * @return void
 */
    protected function _setCache()
    {
        $frontendOptions = array(
            'lifetime' => 3600*4,
            'automatic_serialization' => true
        );

        $backendOptions = array(
            'cache_dir' => sys_get_temp_dir()
        );

        if (array_key_exists('cache', $this->settings) && is_array($this->settings['cache'])) {
            if (array_key_exists('frontendOptions', $this->settings['cache'])) {
                $frontendOptions = array_merge($frontendOptions, $this->settings['cache']['frontendOptions']);
            }

            if (array_key_exists('backendOptions', $this->settings['cache'])) {
                $backendOptions = array_merge($backendOptions, $this->settings['cache']['backendOptions']);
            }
        }

        $this->cache = Zend_Cache::factory(
            'Core',
            'File',
            $frontendOptions,
            $backendOptions
        );
    }

    protected function _setFolders()
    {
        $this->_lessFolders['default'] = $this->settings['sourceFolder']?
            $this->settings['sourceFolder']:
            APPLICATION_PATH  . DIRECTORY_SEPARATOR . 'less' . DIRECTORY_SEPARATOR;

        if (!file_exists($this->_lessFolders['default'])) {
            mkdir($this->_lessFolders['default']);
        }

        $this->_cssFolders['default'] = $this->settings['targetFolder']?
            $this->settings['targetFolder']:
            rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR;

        if (!file_exists($this->_cssFolders['default'])) {
            mkdir($this->_cssFolders['default']);
        }
    }

    public function generateCss()
    {
        $generatedFiles = array();
        $cacheKey = md5(APPLICATION_PATH . __CLASS__);

        $optionalConditions =
            (defined('APPLICATION_ENV') && 'production' !== APPLICATION_ENV) ||
            $this->settings['autoRun'] ||
            $this->settings['forceCompiling'] ||
            ($this->cache->load($cacheKey)) === false;

        if ($this->enabled && $optionalConditions) {
            foreach ($this->_lessFolders as $key => $lessFolder) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveRegexIterator(
                        new RecursiveDirectoryIterator(
                            $lessFolder, FilesystemIterator::SKIP_DOTS
                        ),
                        '/^(?!.*(\/inc|\.cvs|\.svn|\.git)).*$/', RecursiveRegexIterator::MATCH
                    ),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($files as $file) {
                    $lessFile = $file->getRealPath();
                    $cssFile = $this->_cssFolders[$key] . $file->getBasename('.less') . '.css';

                    if ($this->_autoCompileLess($lessFile, $cssFile)) {
                        $generatedFiles[] = $cssFile;
                    }
                }
            }

            $this->cache->save(true, $cacheKey);
        }

        return $generatedFiles;
    }

    protected function _autoCompileLess($inputFile, $outputFile)
    {
        $cacheKey = md5(DIRECTORY_SEPARATOR . __CLASS__ . str_replace(APPLICATION_PATH, null, $outputFile));

        /**
         * Get the cached contents for the current input-file
         */
        if (($cache = $this->cache->load($cacheKey)) === false) {
            $cache = $inputFile;
        }

        /**
         * Compile a new version of the current input file
         */
        $lessCompiler = new Less_Library_LessCompiler();
        $lessCompiler->setFormatter($this->settings['formatter']);

        if (is_bool($this->settings['preserveComments'])) {
            $lessCompiler->setPreserveComments($this->settings['preserveComments']);
        }

        if ($this->settings['variables']) {
            $lessCompiler->setVariables($this->settings['variables']);
        }

        $newCache = $lessCompiler->cachedCompile($cache, $this->settings['forceCompiling']);

        if (true === $this->settings['forceCompiling'] ||
            !is_array($cache) ||
            $newCache["updated"] > $cache["updated"]) {
            $this->cache->save($newCache, $cacheKey);
            file_put_contents($outputFile, $newCache['compiled']);

            return true;
        }

        return false;
    }
}