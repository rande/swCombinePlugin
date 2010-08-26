<?php
/*
 * This file is part of the swCombinePlugin package.
 *
 * (c) 2009-2010 Thomas Rabaix <thomas.rabaix@soleoweb.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function sw_get_javascripts()
{
  $params  = sfConfig::get('app_swToolbox_swCombine', array('version' => false));
  $version = $params['version'];
  
  $response = sfContext::getInstance()->getResponse();
  $included_files = $response->getCombinedAssets();
  
  sfConfig::set('symfony.asset.javascripts_included', true);

  $html = '';
  foreach ($response->getJavascripts() as $file => $options)
  {
    // avoid loading combined files
    if(in_array($file, $included_files))
    {

     continue;
    }
     
    // append version if version is set
    // or if the url does not contains a `?`
    $file = $version && strpos($file, '?') === false ? $file.'?v='.$version : $file;
    
    $html .= javascript_include_tag($file, $options);
  }

  return $html;
}

function sw_get_stylesheets()
{
  $params  = sfConfig::get('app_swToolbox_swCombine', array('version' => false));
  $version = $params['version'];
  
  $response = sfContext::getInstance()->getResponse();
  $included_files = $response->getCombinedAssets();
  
  sfConfig::set('symfony.asset.stylesheets_included', true);

  $html = '';
  foreach ($response->getStylesheets() as $file => $options)
  {
    // avoid loading combined files
    if(in_array($file, $included_files))
    {

     continue;
    }
    
    // append version if version is set
    // or if the url does not contains a `?`
    $file = $version && strpos($file, '?') === false ? $file.'?v='.$version : $file;
    
    $html .= stylesheet_tag($file, $options);
  }

  return $html;
}

function sw_include_stylesheets()
{
  echo sw_get_stylesheets();
}

function sw_include_javascripts()
{
  echo sw_get_javascripts();  
}

function sw_combine_debug()
{
  if(ProjectConfiguration::getActive()->isDebug())
  {
    $response = sfContext::getInstance()->getResponse();
    echo "<!-- DEBUG MODE - \nCombined information : \n";
    foreach($response->getCombinedAssets() as $information)
    {
      echo $information."\n";
    } 
    echo "\n-->\n";
  }
}