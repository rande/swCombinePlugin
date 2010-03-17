<?php
/*
 * This file is part of the swCombinePlugin package.
 *
 * (c) 2008 Thomas Rabaix <thomas.rabaix@soleoweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @package    swCombinePlugin
 * @author     Thomas Rabaix <thomas.rabaix@soleoweb.com>
 * @version    SVN: $Id$
 */
class swCombineStylesheet extends swCombineBase
{
  
  protected
    $paths = array(),
    $path_pos = -1;
 
  
  // fix import
  public function getContents($asset)
  {
    // reset state variables
    $this->path_pos = -1;
    $this->paths    = array();
    
    $include = @file_get_contents($asset);
    
    if(!$include)
    {
      throw new Exception('unable to read the asset : '.$asset);
    }
    
    $contents = $this->removeBom($contents);
    
    $contents = $this->fixImportStatement($path, $include);
    
    // get the version, otherwise set to the current time
    // so each time the cache is cleared then the image are reload from the webserver
    $version = sfConfig::get('app_sfCombinePlugin_asset_version', strtotime('now'));
    
    $pattern = '/url\(("|\'|)(.*)("|\'|)\)/smU';
    $replacement = "url(\${2}?${version})";
    
    $contents = preg_replace($pattern, $replacement, $contents, -1);
    
    // remove the '@CHARSET UTF-8'
    $charset_pattern = '/@charset ([^;]*);/i';
    $contents = preg_replace($charset_pattern, '', $contents);
    
    return $contents;
  }
  
  public function fixImportStatement($path, $include)
  {
    
    $this->path_pos++;

    // store the current path in the recursion
    $this->paths[$this->path_pos] = $path;
    
    // fix image path
    $content = preg_replace_callback('/url\(("|\'|)(.*)("|\'|)\)/smU', array($this, 'fixImportImageCallback'), $include);
    
    // fetch the contents of any include file
    $content = preg_replace_callback('/@import url\([ ]*[\'|"](.*)[\'|"][ ]*\);/smU', array($this, 'fixImportStatementCallback'), $content);
    
    $this->path_pos--;
    
    if(!$content)
    {
      
      return $include; // no match in the recursion
    }
    
    return $content;
  }
  
  public function fixImportImageCallback($matches)
  {
    // have to find a better regular expression
    // ignore @import statements as there are handled by fixImportStatementCallback
    if(preg_match('/\.css/', $matches[2]))
    {
      
      return  $matches[0];
    }
    
    // fix image path 
    $web_dir = sfConfig::get('sf_web_dir');
    
    $infos = pathinfo($this->paths[$this->path_pos]);
    
    $file = $infos['dirname'].'/'.($matches[2]{0} == '/' ? substr($matches[2], 1) : $matches[2]);

    $file = str_replace($web_dir, '', $file);

    return 'url('.$file.')';
  }
  
  /**
   * This might have other issue with related pictures
   */
  public function fixImportStatementCallback($matches)
  {
    $infos = pathinfo($this->paths[$this->path_pos]);
    
    $file = $infos['dirname'].'/'.$matches[2];
    
    $content = @file_get_contents($file);

    if($content)
    {
      
      // return $content;
      return $this->fixImportStatement($file, $content);
    }
    
    // cannot fix import
    return $matches[0]."/* cannot fix import statement */";
  }
}
