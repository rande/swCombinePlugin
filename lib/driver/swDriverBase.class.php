<?php
/**
 * npAssetsOptimizerPlugin base optimization driver class
 *
 * @package     swAssetsOptimizerPlugin
 * @subpackage  driver
 * @author      Nicolas Perriault <nperriault@gmail.com>
 */
abstract class swDriverBase
{
  protected 
    $options          = array(),
    $optimizedContent = null,
    $optimizedSize    = null,
    $originalSize     = null,
    $processed        = false;
  
  abstract public function doProcessFile($file, $replace = false);
  
  public function getOptimizationRatio()
  {
    return 0 !== $this->originalSize ? round($this->optimizedSize * 100 / $this->originalSize, 2) : null;
  }
  
  public function getResults()
  {
    if (!$this->processed)
    {
      throw new LogicException('Optimization has not been processed');
    }
    
    return array(
      'optimizedContent' => $this->optimizedContent,
      'originalSize'     => $this->originalSize,
      'optimizedSize'    => $this->optimizedSize,
      'ratio'            => $this->getOptimizationRatio(),
    );
  }
  
  public function processFile($file, $replace = false)
  {
    $this->originalSize = filesize($file);
    
    $result = $this->doProcessFile($file, $replace);
    
    if ($replace)
    {
      clearstatcache();
      
      $this->optimizedSize = filesize($result);
    }
    else
    {
      $this->optimizedSize = strlen($result);
      
      $this->optimizedContent = $result;
    }
    
    $this->processed = true;
    
    return $this;
  }
  
  protected function replaceFile($file, $content)
  {
    copy($file, $file.'.tmp');
    
    if (!file_put_contents($file, $content))
    {
      throw new RuntimeException(sprintf('Unable to replace file "%s" with optimized contents', $file));
    }
    
    unlink($file.'.tmp');
    
    return $file;
  }
  
  /**
   * Resets driver instance
   *
   * @return npDriverBase
   */
  public function reset()
  {
    $this->optimizedContent = null;
    $this->optimizedSize    = null;
    $this->originalSize     = null;
    $this->processed        = false;
    
    return $this;
  }
}
