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
    $stylesheets = $this->combineValues('stylesheet', $stylesheets, $viewName);
      
    $css = $this->addAssets('Stylesheet', $stylesheets);
  
    // Merge the current view's javascripts with the app's default javascripts
    $javascripts = $this->mergeConfigValue('javascripts', $viewName);
    $javascripts = $this->combineValues('javascript', $javascripts, $viewName);
    
    $js = $this->addAssets('Javascript', $javascripts);
  
    return implode("\n", array_merge($css, $js))."\n";
  }
  
  public function combineValues($type, $values)
  {
    $combined = $final = array();

    $packages_files = array();
    
    // build the package assets
    foreach($this->getParameterHolder()->get('configuration['.$type.'][packages]', array()) as $name => $package)
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

    // build the combined assets
    foreach($values as $value)
    {
      $assert_name = is_array($value) ? key($value) : $value;
      
      if(in_array($assert_name, $packages_files))
      {
        // the file is present in a package file, skip it
        continue;
      }
      
      if($this->excludeFile($type, $assert_name))
      {
        $final[] = $value;
        continue;
      }

      if(!$this->isCombinable($type, $value))
      {
        $final[] = $value;
        continue;
      }
      
      $combined[] = $value;
    }
    
    if(count($combined) > 0)
    {
      $final[] = sprintf('%s/%s', 
        $this->getParameterHolder()->get('public_path'),
        $this->getCombinedName($type, $combined)
      );
    }
    
    return $final;
  }
  
  public function combineAssets($type, $assets)
  {
    
    $combined = $final = array();
    
    // build the package assets
    
    foreach($this->getParameterHolder()->get('configuration['.$type.'][packages]', array()) as $name => $package)
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
    // TODO : add the media revision number in the hash
    $format = $this->getParameterHolder()->get('configuration['.$type.'][filename]', '%s');

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
    
    return sprintf($format, md5(serialize($assets)));
  }
  
  public function getPackageName($type, $name)
  {
    // TODO : add the media revision number in the hash
    $format = $this->getParameterHolder()->get('configuration['.$type.'][filename]', '%s');
     
     // var_dump(sprintf($format, md5(sfInflector::underscore('package_'.$type.'_'.$name)))); die();
    return sprintf($format, md5(sfInflector::underscore('package_'.$type.'_'.$name)));
  }
  
  /**
   * Merges configuration values for a given key and category.
   *
   * @param string $keyName  The key name
   * @param string $category The category name
   *
   * @return string The value associated with this key name and category
   */
  public function exposeMergeConfigValue($yamlConfig, $keyName, $category)
  {
    return $this->mergeConfigValue($yamlConfig, $keyName, $category);
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
    $exclude = $this->getParameterHolder()->get('configuration['.$type.'][exclude]', array());
    
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
          $tmp[$key] = $tmp[$key] = sprintf("  \$response->add%s('%s', '%s', %s);", $type, $key, $position, str_replace("\n", '', var_export($options, true)));
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