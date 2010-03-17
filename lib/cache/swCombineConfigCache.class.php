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
class swCombineConfigCache extends sfConfigCache
{
  /**
   * Returns the config handler configured for the given name
   *
   * @param string $name The config handler name
   *
   * @return sfConfigHandler A sfConfigHandler instance
   */
  public function getHandler($name)
  {
    if (count($this->handlers) == 0)
    {
      // we need to load the handlers first
      $this->loadConfigHandlers();
    }
    
    if (is_array($this->handlers[$name]))
    {
      $class = $this->handlers[$name][0];
      $this->handlers[$name] = new $class($this->handlers[$name][1]);
    }

    return $this->handlers[$name];
  }
  
}