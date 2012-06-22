<?php

/**
 * PluginMedia
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class PluginMedia extends BaseMedia
{
  public function preInsert($event)
  {
    if (!$this['hash'])
    {
      $this['hash'] = sha1($this['media_url']);
    }
  }
}