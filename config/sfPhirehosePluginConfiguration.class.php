<?php
class sfPhirehosePluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
    }
}


/**
 * Convert a php stdClass to an array recursively
 * 
 * @param stdClass $class
 * @return array
 */
if (!function_exists('object_to_array'))
{
  function object_to_array(stdClass $Class)
  {
    // Typecast to (array) automatically converts stdClass -> array.
    $Class = (array) $Class;
   
    // Iterate through the former properties looking for any stdClass properties.
    // Recursively apply (array).
    foreach ($Class as $key => $value)
    {
      if ($value instanceof stdClass)
      {
        $Class[$key] = object_to_array($value);
      }
    }
    
    return $Class;
  }
}