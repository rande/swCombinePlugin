<?php
/*
 * This file is part of the swCombinePlugin package.
 *
 * (c) 2009-2010 Thomas Rabaix <thomas.rabaix@soleoweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class swCombinePluginConfiguration extends sfPluginConfiguration
{
  protected $loaded_assets = array();
  
  public function initialize()
  {
    
    $this->dispatcher->connect('response.method_not_found', array($this, 'listenToMethodNotFound'));
  }
  
  public function listenToMethodNotFound(sfEvent $event)
  {
    $parameters = $event->getParameters();
    
    if($parameters['method'] == 'defineCombinedAssets')
    {
      $this->loaded_assets = count($parameters['arguments']) > 0 ? $parameters['arguments'][0] : array();
      
      $event->setProcessed(true);
    }
    
    if($parameters['method'] == 'getCombinedAssets')
    {
      $event->setReturnValue($this->loaded_assets);
      $event->setProcessed(true);
    }
  }
}