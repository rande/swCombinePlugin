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
class swCombineViewConfigHandler extends sfViewConfigHandler
{

  protected
    $assets_loaded = array();
    
  /**
   * Adds stylesheets and javascripts statements to the data.
   *
   * @param string $viewName The view name
   *
   * @return string The PHP statement
   */
  protected function addHtmlAsset($viewName = '')
  {
    
    // Merge the current view's stylesheets with the app's default stylesheets
    $stylesheets = $this->mergeConfigValue('stylesheets', $viewName);
    // clean stylesheet list (-*)
    $stylesheets = $this->addAssets('stylesheets', $stylesheets, false);

    // combine
    $stylesheets = $this->combineValues('stylesheet', $stylesheets, $viewName);

    $css = $this->addAssets('Stylesheet', $stylesheets);
  
    // Merge the current view's javascripts with the app's default javascripts
    $javascripts = $this->mergeConfigValue('javascripts', $viewName);
    
    // clean stylesheet list (-*)
    $javascripts = $this->addAssets('javascripts', $javascripts, false);
    
    // combine
    $t_javascripts = $this->combineValues('javascript', $javascripts, $viewName);

    // make sure the combined javascripts are always loaded first
    $javascripts = array();    
    foreach($t_javascripts as $javascript)
    {
      $javascripts[] = array($javascript => array('position' => 'first'));
    }
    
    $js = $this->addAssets('Javascript', $javascripts);
  
    // set current js and css loaded, also add information about the current defined assets
    return implode("\n", array_merge($css, $js)).
      "\n  \$response->defineCombinedAssets(".var_export($this->assets_loaded, 1).");\n";
  }
  
  public function combineValues($type, $values, $viewName)
  {
    $combined = $final = array();


    $environment = sfContext::getInstance()->getConfiguration()->getEnvironment();
    if($environment == 'dev'){
        $final = array();
        foreach($values as $value){
            $final[] = $value[1];
        }

        return $final;
    }


    $packages_files = array();
    
    $configuration = $this->getParameterHolder()->get('configuration');
    $packages = isset($configuration[$type]['packages']) ? $configuration[$type]['packages'] : array();
    $public_path =  isset($configuration[$type]['public_path']) ? $configuration[$type]['public_path'] : '/sw-combine';
    
    // build the package assets
    foreach($packages as $name => $package)
    {
      if(isset($package['auto_include']) && $package['auto_include'])
      {
        $final[] = sprintf('%s/%s', 
          $this->getParameterHolder()->get('public_path'),
          $this->getPackageName($type, $name)
        );
        $packages_files = array_merge($packages_files, $package['files']);
      }
    }
    
    // load packages defined in the yaml
    if($viewName == '')
    {
      $viewName = 'all';
    }
    
    if(
      isset($this->yamlConfig[$type]) && 
      isset($this->yamlConfig[$viewName]['sw_combine']) && 
      isset($this->yamlConfig[$viewName]['sw_combine']['include_packages']) && 
      isset($this->yamlConfig[$viewName]['sw_combine']['include_packages'][$type])
    )
    {
      foreach($this->yamlConfig[$viewName]['sw_combine']['include_packages'][$type] as $package_name)
      {
        if(!isset($packages[$package_name]))
        {
          continue;
        }
        
        $final[] = sprintf('%s/%s', $public_path, $this->getPackageName($type, $name));
        
        $packages_files = array_merge($packages_files, $package['files']);
      }
    }
  
    // build the combined assets
    foreach($values as $value)
    {
      $asset_name = $value[1];
      
      if(in_array($asset_name, $packages_files))
      {
        // the file is present in a package file, skip it
        continue;
      }
      
      if($this->excludeFile($type, $asset_name))
      {
        
        $final[] = $asset_name;
        continue;
      }

      if(!$this->isCombinable($type, $asset_name))
      {

        $final[] = $asset_name;
        continue;
      }

      $combined[] = $asset_name;
    }
    
    if(count($combined) > 0)
    {
      $final[] = sprintf('%s/%s', $public_path, $this->getCombinedName($type, $combined));
    }
    
    // keep a track of combined filed files for this view
    $this->assets_loaded = array_merge($this->assets_loaded, $packages_files, $combined);
    
    return $final;
  }
  
  public function combineAssets($type, $assets)
  {
    
    $combined = $final = array();
    
    // build the package assets
    $configuration = $this->getParameterHolder()->get('configuration');
    $packages = isset($configuration[$type]['packages']) ? $configuration[$type]['packages'] : array();
    
    foreach($packages as $name => $package)
    {
      if(isset($package['auto_include']) && $package['auto_include'])
      {
        $final[] = array(
          0 => $type,
          1 => $this->getPackageName($type, $name),
          2 => false,
          3 => false
        );
      }
    }
    
    // build the combined assets
    foreach($assets as $asset)
    {
      if($this->excludeFile($type, $asset[1]))
      {
        $final[] = $asset;
        continue;
      }
      
      $combined[] = $asset[1];
    }
    
    if(count($combined) > 0)
    {
      $final[] = array(
        0 => $type,
        1 => $this->getCombinedName($type, $assets),
        2 => false,
        3 => false
      );
    }

    return $final;
  }
    
  public function getCombinedName($type, array $assets)
  {
    $configuration = $this->getParameterHolder()->get('configuration');
    $format = isset($configuration[$type]['filename']) ? $configuration[$type]['filename'] : '%s';
    
    // make sure we have a flat list
    foreach($assets as $pos => $asset)
    {
      if(is_array($assets[$pos]))
      {
        $assets[$pos] = key($asset);
      }
    }
        
    // make sure the array is always the same
    $assets = array_unique($assets);
    sort($assets);
    

    // compute the name
    $name =  md5(serialize($assets));

    return sprintf($format, $name);
  }
  
  public function getPackageName($type, $name)
  {
    $configuration = $this->getParameterHolder()->get('configuration');
    $format  = isset($configuration[$type]['filename']) ? $configuration[$type]['filename'] : '%s';
    
    $name    = md5(sfInflector::underscore('package_'.$type.'_'.$name));
    
    return sprintf($format, $name);
  }
  
  /**
   * Merges configuration values for a given key and category.
   *
   * @param string $keyName  The key name
   * @param string $category The category name
   *
   * @return string The value associated with this key name and category
   */
  public function exposeMergeConfigValue($keyName, $category)
  {
    return $this->mergeConfigValue($keyName, $category);
  }
  
  public function setYamlConfig($config)
  {
    $this->yamlConfig = $config;
  }
  
  public function isCombinable($type, $file)
  {
    if(is_array($file))
    {
      $file = current($file);

      if(isset($file['media']) && $file['media'] != 'screen')
      {
        return false;
      }
    }
    
    return true;
  }
  
  public function excludeFile($type, $file)
  {
    
    $configuration = $this->getParameterHolder()->get('configuration');
    $exclude = isset($configuration[$type]['exclude']) ? $configuration[$type]['exclude'] : array();
    
    if(in_array($file, $exclude))
    {
      
      return true;
    }
    
    return false;
  }
  
  public function exposeAddAssets($type, $assets, $raw_php = true)
  {
    
    return $this->addAssets($type, $assets, $raw_php);
  }
  
  private function addAssets($type, $assets, $raw_php = true)
  {
    $tmp = array();
    foreach ((array) $assets as $asset)
    {
      $position = '';
      if (is_array($asset))
      {
        $key = key($asset);
        $options = $asset[$key];
        if (isset($options['position']))
        {
          $position = $options['position'];
          unset($options['position']);
        }
      }
      else
      {
        $key = $asset;
        $options = array();
      }

      if ('-*' == $key)
      {
        $tmp = array();
      }
      else if ('-' == $key[0])
      {
        unset($tmp[substr($key, 1)]);
      }
      else
      {
        if($raw_php)
        {
          $tmp[$key] = sprintf("  \$response->add%s('%s', '%s', %s);", $type, $key, $position, str_replace("\n", '', var_export($options, true)));
        }
        else
        {
          $tmp[$key] = array($type, $key, $position, $options);
        }
      }
    }
    
    return array_values($tmp);
  }
}
