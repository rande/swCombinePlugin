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
class swCombineJavascript extends swCombineBase
{
  public function getContents($asset)
  {
    // reset state variables
    $this->path_pos = -1;
    $this->paths    = array();
    
    $contents = @file_get_contents($asset);
    
    if(!$contents)
    {
      throw new Exception('unable to read the asset : '.$asset);
    }
    
    $contents = $this->removeBom($contents);
    
    // avoid error with missing ";"
    $contents .= ";\n";
    
    return $contents;
  }
}