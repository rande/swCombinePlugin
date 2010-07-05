<?php
/**
 * swCombinePlugin Google Closure Compiler JAR javascript optimization driver
 *
 * @package     swCombinePlugin
 * @subpackage  driver
 * @author      Thomas Rabaix <thomas.rabaix@soleoweb.com>
 *
 * @see http://code.google.com/intl/en_US/closure/compiler/docs/api-ref.html
 */
class swDriverGoogleClosureCompilerJar extends swDriverBase
{
  public function doProcessFile($file, $replace = false)
  {
    $cmd = sprintf('java -jar %s/swCombinePlugin/lib/vendor/closure/compiler.jar --js=%s', sfConfig::get('sf_plugins_dir'), $file);
        
    $return = false;
    $output = array();
    exec($cmd, $output, $return);
    
    if($return != 0)
    {
      throw new RuntimeException('unable to compile the asset with google closure compiler');
    }
    
    $optimizedContent = implode("\n", $output);
    
    if ($replace)
    {
      return parent::replaceFile($file, $optimizedContent);
    }
    else
    {
      return $optimizedContent;
    }
  }
}

