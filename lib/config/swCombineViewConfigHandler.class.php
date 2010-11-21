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

  protected $debug_output  = array();
  
  public function addDebugInformation($value)
  {
    $this->debug_output[] = $value;
  }
  /**
   * Adds stylesheets and javascripts statements to the data.
   *
   * @param string $viewName The view name
   *
   * @return string The PHP statement
   */
  protected function addHtmlAsset($viewName = '')
  {
    // Disable for dev environment
    if (sfConfig::get('sf_debug') || sfConfig::get('sf_test'))
    {
      return parent::addHtmlAsset($viewName);
    }

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
    $javascripts = $this->combineValues('javascript', $javascripts, $viewName);

    $js = $this->addAssets('Javascript', $javascripts);
  
    // set current js and css loaded, also add information about the current defined assets
    return implode("\n", array_merge($css, $js)).
      "\n  \$response->defineCombinedAssets(".var_export($this->debug_output, 1).");\n";
  }
  
  /**
   * Merges configuration values for a given key and category for packages.
   *
   * @param string $keyName  The key name
   * @param string $category The category name
   *
   * @return string The value associated with this key name and category
   */
  protected function mergePackageConfigValue($keyName, $category)
  {
    $values = array();
    $keyName = $keyName.'s'; // fix bug due to wrong naming convention
  
    if (isset($this->yamlConfig['all']['sw_combine']['include_packages'][$keyName]) && is_array($this->yamlConfig['all']['sw_combine']['include_packages'][$keyName]))
    {
      $values = $this->yamlConfig['all']['sw_combine']['include_packages'][$keyName];
    }

    if ($category && isset($this->yamlConfig[$category]['sw_combine']['include_packages'][$keyName]) && is_array($this->yamlConfig[$category]['sw_combine']['include_packages'][$keyName]))
    {
      $values = array_merge($values, $this->yamlConfig[$category]['sw_combine']['include_packages'][$keyName]);
    }
        
    return $values;
  }
  
  public function combineValues($type, $values, $viewName)
  {
    $combined_media = $final = array();

    $packages_files = array();
    
    $configuration  = $this->getParameterHolder()->get('configuration');
    $packages       = isset($configuration[$type]['packages']) ? $configuration[$type]['packages'] : array();
    $public_path    = isset($configuration[$type]['public_path']) ? $configuration[$type]['public_path'] : '/sw-combine';
    
    // build the package assets
    foreach($packages as $name => $package)
    {
      if(isset($package['auto_include']) && $package['auto_include'])
      {
        $settings = array();
        $filename = sprintf('%s/%s', $public_path, $this->getPackageName($type, $name));
        
        if($type == 'stylesheet')
        {
          $settings[$filename] = array('media' => 'screen');
        }
        else if($type == 'javascript')
        {
          $settings[$filename] = array('position' => 'first');
        }
        
        $final[] = $settings;
        
        $packages_files = array_merge($packages_files, $package['files']);
        
        $this->addDebugInformation(sprintf('{package} `%s` (auto-include) : %s => %s', $name, $filename, var_export($package['files'], 1)));
      }
    }
    
    // load packages defined in the yaml
    if($viewName == '')
    {
      $viewName = 'all';
    }
    
    $include_packages = $this->mergePackageConfigValue($type, $viewName);
  
    foreach($include_packages as $package_name)
    {
      if(!isset($packages[$package_name]))
      {
        continue;
      }
      
      $filename = sprintf('%s/%s', $public_path, $this->getPackageName($type, $package_name));
      $settings = array();
      
      if($type == 'stylesheet')
      {
        $settings[$filename] = array('media' => 'screen');
      }
      else if($type == 'javascript')
      {
        $settings[$filename] = array('position' => 'first');
      }
      
      $final[] = $settings;
      
      $packages_files = array_merge($packages_files, $packages[$package_name]['files']);
      
      $this->addDebugInformation(sprintf('{package} `%s` : %s => %s', $package_name, $filename, var_export($packages[$package_name]['files'], 1)));
    }

    // build the combined assets
    foreach($values as $value)
    {
      $asset_name = $value[1];
      
      if($type == 'stylesheet')
      {
        $media = array_key_exists('media', $value[3]) ? $value[3]['media'] : 'screen';

        $settings = array($asset_name => array(
          'media' => $media
        ));
      }
      else if($type == 'javascript')
      {
        $media = array_key_exists('media', $value[3]) ? $value[3] : '';
        $settings = array($asset_name => array(
          'position' => $media
        ));
      }
      
      if(in_array($asset_name, $packages_files))
      {
        // the file is present in a package file, skip it
        continue;
      }
      
      if($this->excludeFile($type, $asset_name))
      {
        $final[] = $settings;
        
        continue;
      }

      if(!$this->isCombinable($type, $asset_name))
      {
        $final[] = $settings;
        
        continue;
      }
      
      if($type == 'stylesheet')
      {
        if(!isset($combined_media[$media]))
        {
          $combined_media[$media] = array();
        }
        
        $combined_media[$media][] = $value[1];
      }
      else if($type == 'javascript')
      {
        $combined_media[] = $value[1];
      }
    }
    
    // compute the path for each media type
    if($type == 'stylesheet')
    {
      foreach($combined_media as $media => $asset_names)
      {
        if(count($asset_names) > 0)
        {
          $filename = sprintf('%s/%s', $public_path, $this->getCombinedName($type, $asset_names));
          $final[] = array(
            $filename => array(
              'media' => $media, // for now package works only for screen media
            )
          );

          $this->addDebugInformation(sprintf('{stylesheets} media `%s` : %s => %s', $media, $filename, var_export($asset_names, 1)));
        }
      }
    }
    else if($type == 'javascript')
    {
      if(count($combined_media) > 0)
      {
        $filename = sprintf('%s/%s', $public_path, $this->getCombinedName($type, $combined_media));
        $final[] = array(
          $filename => array(
            'position' => '',
          )
        );
        
        $this->addDebugInformation(sprintf('{javascript} %s => %s', $filename, var_export($combined_media, 1)));
      }
    }
    
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
    
    if($assets == null)
    {
      throw new sfException('$assets cannot be null');
    }
    
    // make sure we have a flat list
    foreach($assets as $pos => $asset)
    {
      if(is_array($asset))
      {
        $assets[$pos] = $asset[1];
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
        if (array_key_exists('position', $options))
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
