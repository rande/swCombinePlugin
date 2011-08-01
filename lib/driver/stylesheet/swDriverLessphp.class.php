<?php
/**
 * Lessphp driver for swCombinePlugin
 * See https://github.com/leafo/lessphp
 *
 * @package     swCombinePlugin
 * @subpackage  driver
 * @author      Yohan Giarelli <yohan@giarelli.org>
 */
class swDriverLessphp extends swDriverBase
{
  public function doProcessFile($file, $replace = false)
  {
    $lessCompiler = new lessc;
    $optimizedContent = $lessCompiler->parse(file_get_contents($file));
    
    return $replace ? parent::replaceFile($file, $optimizedContent) : $optimizedContent;
  }
}