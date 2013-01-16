<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

define('BOOTSTRAP_FILE', basename(__FILE__));
define('ONTOWIKI_ROOT', realpath(dirname(__FILE__) . '/../../..') . '/');
define('APPLICATION_PATH', ONTOWIKI_ROOT . 'application/');
define('APPLICATION_ENV', 'unittesting');
define('ONTOWIKI_REWRITE', false);
define('CACHE_PATH', ONTOWIKI_ROOT . 'cache'.DIRECTORY_SEPARATOR);

// path to tests
define('_TESTROOT', rtrim(dirname(__FILE__), '/') . '/');

// path to OntoWiki
define('_OWROOT', ONTOWIKI_ROOT);

// add libraries to include path
$includePath  = get_include_path()                                  . PATH_SEPARATOR;
$includePath .= _TESTROOT                                           . PATH_SEPARATOR;
$includePath .= ONTOWIKI_ROOT . 'application/classes/'              . PATH_SEPARATOR;
$includePath .= ONTOWIKI_ROOT . 'libraries/'                        . PATH_SEPARATOR;
$includePath .= ONTOWIKI_ROOT . 'libraries/Zend/'                   . PATH_SEPARATOR;
$includePath .= ONTOWIKI_ROOT . 'libraries/Erfurt/library/'         . PATH_SEPARATOR;
$includePath .= ONTOWIKI_ROOT . 'libraries/Erfurt/tests/unit'       . PATH_SEPARATOR; // for test base class
$includePath .= __DIR__       . '/../Model/'                        . PATH_SEPARATOR;
set_include_path($includePath);

// start dummy session before any PHPUnit output
require_once 'Zend/Session/Namespace.php';
$session = new Zend_Session_Namespace('OntoWiki_Test');

// Zend_Loader for class autoloading
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();
$loader->registerNamespace('OntoWiki_');
$loader->registerNamespace('Erfurt_');
$loader->registerNamespace('Zend_');
$loader->registerNamespace('PHPUnit_');
$loader->registerNamespace('PubSubHubbub_');

/** OntoWiki */
require_once 'OntoWiki.php';
