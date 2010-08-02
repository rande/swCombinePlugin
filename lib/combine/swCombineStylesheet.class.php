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
    
    // import css from external declarations
    $contents = $this->fixImportStatement($asset);
    
    // get the version so each time the cache is cleared then the image are reload 
    // from the webserver
    $params  = sfConfig::get('app_swToolbox_swCombine', array('version' => false));
    $version = $params['version'];
    if($version)
    {
      $pattern = '/url\(("|\'|)(.*)("|\'|)\)/smU';
      $replacement = "url(\${2}?_sw=${version})";

      $contents = preg_replace($pattern, $replacement, $contents, -1);
    }
    
    // remove the '@CHARSET UTF-8'
    $charset_pattern = '/@charset ([^;]*);/i';
    $contents = preg_replace($charset_pattern, '', $contents);
    
    return $contents;
  }
  
  public function fixImportStatement($asset)
  {
    $contents = @file_get_contents($asset);
    
    if(!$contents)
    {
      throw new Exception('unable to read the asset : '.$asset);
    }
    
    // remove BOM
    $contents = $this->removeBom($contents);
    
    $asset = realpath($asset);
    $info = pathinfo($asset);
    
    $this->path_pos++;

    // store the current path in the recursion
    $this->paths[$this->path_pos] = $info['dirname'];
    
    // fix image path
    $fix = preg_replace_callback('/url\(("|\'|)(.*)("|\'|)\)/smU', array($this, 'fixImportImageCallback'), $contents);
    
    if($fix)
    {
      $contents = $fix;
    }
    
    // fetch the contents of any include files
    $fix = preg_replace_callback('/@import url\([ ]*[\'|"](.*)[\'|"][ ]*\);/smU', array($this, 'fixImportStatementCallback'), $contents);

    if($fix)
    {
      $contents = $fix;
    }
    
    $this->path_pos--;
        
    return $contents;
  }
  
  public function fixImportImageCallback($matches)
  {
    // have to find a better regular expression
    // ignore @import statements as there are handled by fixImportStatementCallback
    if(preg_match('/\.css/', $matches[2]))
    {
      
      return $matches[0];
    }
    
    $file       = false;
    $web_dir    = sfConfig::get('sf_web_dir');
    $plugin_dir = sfConfig::get('sf_plugins_dir');

    // absolute path
    if($matches[2]{0} == '/')
    {
      $file = $matches[2];
    }
    // external file
    else if(substr($matches[2], 0, 7) == 'http://' || substr($matches[2], 0, 8) == 'https://')
    {
      $file = $matches[2];
    }
    // local file, fix path
    else
    {
      $file = $this->paths[$this->path_pos].'/'.$matches[2];
      $file = realpath($file);
      
      // remove path if file are in the web directory
      if(strpos($file, $web_dir) === 0)
      {
        $file = str_replace($web_dir, '', $file); 
      }
      else if(strpos($file, $plugin_dir) === 0)
      {
        $file = str_replace($plugin_dir, '', $file); 
        $file = preg_replace('|(/[^/]+)/web|', '\1', $file, -1);
      }
      else
      {
        $file = false;
      }
    }
    
    if($file)
    {
      return 'url('.$file.')';
    }
    
    $this->logSection('fix-image', 'unable to find the file : '.$matches[2]);
    
    return 'none';
  }
  
  /**
   * This might have other issue with related pictures
   */
  public function fixImportStatementCallback($matches)
  {
    $infos = $this->paths[$this->path_pos];

    $file = $this->paths[$this->path_pos].'/'.$matches[1];
    
    $content = @file_get_contents($file);

    if($content)
    {
      
      return $this->fixImportStatement($file, $content);
    }
    
    throw new sfException('Unable to import statement file : '.$file);
  }
}
