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
  sfConfig::set('symfony.asset.javascripts_included', true);

  $html = '';
  foreach ($response->getJavascripts() as $file => $options)
  {
    $file = $version ? $file.'?v='.$version : $file;
    
    $html .= javascript_include_tag($file, $options);
  }

  return $html;
}

function sw_get_stylesheets()
{
  $params  = sfConfig::get('app_swToolbox_swCombine', array('version' => false));
  $version = $params['version'];
  
  $response = sfContext::getInstance()->getResponse();
  sfConfig::set('symfony.asset.stylesheets_included', true);

  $html = '';
  foreach ($response->getStylesheets() as $file => $options)
  {
    $file = $version ? $file.'?v='.$version : $file;
    
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

