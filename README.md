#ZendLess

ZendLess is a ZendFramework module to (automatically) compile less-files (http://lesscss.org/) by using lessc.php (http://leafo.net/lessphp/).

## Requirements

The master branch has the following requirements:

* ZendFramework 1.10 or greater.
* PHP 5.3.0 or greater.

## Installation

* Clone/Copy the files in your application's module directory
    * When you don't have a module directory, create a module directory, for example in APPLICATION_PATH/modules and update the configuration file.
    ie. for application.ini as a configuration file we would use
    resources.frontController.moduleDirectory = APPLICATION_PATH "/modules"
    resources.modules[] = ""
* The Less module will run immediately.

## Documentation

The component will check for less-files to (re)compile automatically when:
 * When the application is not in production mode
 * autoRun is set to true in the component settings
 * Cache-time expires

In a production environment one can force the component to (re)compile all less-files by supplying forceLessToCompile=true in the request string.

The component caches the compiled files with the help of Zend_Cache.
All less-files should be placed in the `application/less` directory (to generate css-files in the default `public/css` directory).

The default duration time for the cache is 4 hours.
After that time the cache expires and after a new request the component will check for updated or added less-files.

### Possible Component Settings

The Less module has a couple of settings that can be set in your `application.ini`:

    Less.sourceFolder = null                        // Where to look for Less files (Default: APPLICATION_PATH/less)
    Less.targetFolder = null                        // Where to put the generated css (Default: DOCUMENT_ROOT/css)
    Less.formatter = 'compressed'                   // lessphp compatible formatter
    Less.preserveComments = null                    // Preserve comments or remove them (Default: remove comments)
    Less.forceCompiling = false                     // Always recompile Less files
    Less.autoRun = false                            // Check if compilation is necessary, this ignores the CakePHP Debug setting
    Less.cache.frontendOptions.lifetime = 14400     // Cache lifetime (Default: 4 hours)

## License
GNU General Public License, version 3 (GPL-3.0)
http://opensource.org/licenses/GPL-3.0