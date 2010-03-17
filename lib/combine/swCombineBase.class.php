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
abstract class swCombineBase 
{
  protected
    $files = array();
    
    
  public function addFile($file)
  {
    $this->files[] = $file;
  }
  
  public function getFiles()
  {
    
    return $this->files;
  }
  
  public function getContentsFromFiles()
  {
    $contents = '';
    foreach($this->files as $file)
    {
      $contents .= $this->getContents($file)."\n";
    }
    
    return $contents;
  }
  
  public function removeBom($contents)
  {
    
    return str_replace(pack("CCC",0xef,0xbb,0xbf), '', $contents);
  }
  
  abstract function getContents($filename);
}