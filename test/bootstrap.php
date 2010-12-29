<?php
$pc = realpath(dirname(__FILE__).'/../../../config/ProjectConfiguration.class.php');
require_once $pc;

$configuration = ProjectConfiguration::getApplicationConfiguration('frontend', 'test', true);
new sfDatabaseManager($configuration);
  
// autoloader
$autoload = sfSimpleAutoload::getInstance(sfConfig::get('sf_cache_dir').'/project_autoload.cache');
$autoload->loadConfiguration(sfFinder::type('file')->name('autoload.yml')->in(array(
  sfConfig::get('sf_symfony_lib_dir').'/config/config',
  sfConfig::get('sf_config_dir'),
)));

$autoload->register();

// lime
include $configuration->getSymfonyLibDir().'/vendor/lime/lime.php';
