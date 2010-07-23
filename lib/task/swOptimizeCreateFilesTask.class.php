<?php
/*
 * This file is part of the swCombinePlugin package.
 *
 * (c) 2010 Thomas Rabaix <thomas.rabaix@soleoweb.com>
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
class swOptimizeCreateFilesTask extends sfBaseTask
{
  
  protected
    $view_handler,
    $view_parameters;
    
  protected function configure()
   {
     $this->namespace        = 'sw';
     $this->name             = 'combine';
     $this->briefDescription = 'Combine file';

     $this->addArguments(array(
       new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
     ));

     $this->addOptions(array(
       new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'cli'),
       new sfCommandOption('no-confirmation', null, sfCommandOption::PARAMETER_NONE, 'Whether to force deleting the file')
     ));

   }
  
  public function execute($arguments = array(), $options = array())
  {

    // the only way to get the configurated handler
    $config_cache = new swCombineConfigCache($this->configuration);
    $view_handler = $config_cache->getHandler('modules/*/config/view.yml');
    
    if(!$view_handler instanceof swCombineViewConfigHandler)
    {
      
      throw new sfException('The view config handler must be a swCombineViewConfigHandler instance');
    }
    
    $this->view_handler    = $view_handler;
    $this->view_parameters = $view_handler->getParameterHolder();
    
    $this->removeFiles($options['no-confirmation']);
    $this->combineModuleFiles();
    $this->combinePackagesFiles('stylesheet');
    $this->combinePackagesFiles('javascript');

    $this->log('done !');
  }
  
  /**
   * Remove generated files
   */
  public function removeFiles($confirmation)
  {
    $files = sfFinder::type('file')
      ->maxdepth(0)
      ->in($this->view_parameters->get('private_path'));
    
    if(count($files) > 0 && ($confirmation || $this->askConfirmation(array_merge($files, array('--','Are you sure you want to delete these files? (y/N)')), null, false)))
    {
      $this->logSection('combine', 'remove old files');
      $this->getFilesystem()->remove($files);
    }
    
  }
  
  /**
   * Combine files from packages
   */
  public function combinePackagesFiles($type)
  {
    
    $configuration = $this->view_parameters->get('configuration');
    $packages =  isset($configuration[$type]['packages']) ? $configuration[$type]['packages'] : array();
    
    foreach($packages as $name => $package)
    {
      $assets = array();
      foreach($package['files'] as $file)
      {
        $assets[] = array(
          0 => $type,
          1 => $file,
          2 => false,
          3 => false
        );
      }

      if(count($assets) == 0)
      {

        return false;
      }

      $combine_name = $this->view_handler->getPackageName($type, $name);
      
      $this->combineAndOptimize($type, $assets, false, $combine_name);
    }
  }
  
  /**
   * 
   * Combine files defined in module's view.yml files
   */
  public function combineModuleFiles()
  {
    // get the all modules
    $modules = sfConfig::get('sf_enabled_modules', array());

    $app_modules = sfFinder::type('directory')
      ->maxdepth(0)
      ->in(sfConfig::get('sf_app_module_dir'));
    
    foreach($app_modules as $module)
    {
      $modules[] = str_replace(sfConfig::get('sf_app_module_dir').'/', '', $module);
    }


    foreach($modules as $module)
    {
      
      $this->logSection('combine', 'module : '.$module);
      
      $configPath = sprintf('modules/%s/config/view.yml', $module);
      $files      = $this->configuration->getConfigPaths($configPath);
      $config     = swCombineViewConfigHandler::getConfiguration($files);
      
      $this->view_handler->setYamlConfig($config);
      
      foreach($config as $view => $params)
      {
        $this->logSection('combine', ' > view : '.$module.'::'.$view);
        
        // Generate css module files
        $stylesheets = $this->view_handler->exposeMergeConfigValue('stylesheets', $view);
        if(count($stylesheets) > 0)
        {
          $assets = $this->view_handler->exposeAddAssets('stylesheet', $stylesheets, false);
          $this->combineAndOptimize('stylesheet', $assets);
        }
                
        // Generate js module files
        $javascripts = $this->view_handler->exposeMergeConfigValue('javascripts', $view);
        if(count($javascripts) > 0)
        {
          $assets = $this->view_handler->exposeAddAssets('javascript', $javascripts, false);
          $this->combineAndOptimize('javascript', $assets);
        }
      }
    }
  }
  
  /**
   * Combine and optimize asset
   *
   */
  public function combineAndOptimize($type, $assets, $use_ignore = true, $force_name_to = false)
  {
    
    $configuration = $this->view_parameters->get('configuration');
    $combine_class =  $configuration[$type]['combine'];

    if(!class_exists($combine_class))
    {
      
      throw new sfException(sprintf('The combine class %s does not exist', $combine_class));
    }
    
    $combined = array();
    $combine  = null; 
    $combines = array(); 
    foreach($assets as $asset)
    {

      if($type == 'javascript' && !$combine)
      {
        $combine = new $combine_class($this->dispatcher, $this->formatter);
      }
      else
      {
        if(!array_key_exists('media', $asset[3]))
        {
          $asset[3] = array('media' => 'screen');
        }
        
        $media = $asset[3]['media'];
        
        if(!array_key_exists($media, $combines))
        {
          $combines[$media] = new $combine_class($this->dispatcher, $this->formatter);
        }
      }
      
      if($use_ignore && $this->view_handler->excludeFile($type, $asset[1]))
      {
        $this->logSection('combine', '   - exclude : '.$asset[1]);
        
        continue;
      } 
      
      if(!$this->view_handler->isCombinable($type, $asset[1]))
      {
        $this->logSection('combine', '   - not combinable : '.$asset[1]);
                
        continue;
      }
      
      $this->logSection('combine', '   + add : '.$asset[1]);
      
      if($type == 'stylesheet')
      {
        $combines[$media]->addFile($asset[1]);
      }
      else
      {
        $combine->addFile($asset[1]);
      }
    }

    if($type == 'stylesheet')
    {
      foreach($combines as $name => $combine)
      {
        $this->combine($type, $combine, $force_name_to);
      }
    }
    else ($type == 'javascript' && $combine)
    {
      $this->combine($type, $combine, $force_name_to);
    }
  }
  
  public function combine($type, $combine, $force_name_to = false)
  {
    if(count($combine->getFiles()) == 0)
    {
      $this->logSection('combine', '   ~ no files to add : '.$type);

      return;
    }

    $path = sprintf('%s/%s', 
      $this->view_parameters->get('private_path'), 
      $force_name_to ? $force_name_to : $this->view_handler->getCombinedName($type, $combine->getFiles())
    );
    
    if(is_file($path))
    {
      $this->logSection('combine', 'duplicate file, nothing to do');
      return;
    }

    $content = $combine->getContentsFromFiles();
    
    // save combined file
    if(!$this->saveContents($path, $content))
    {
      $this->logSection('combine', '   ~ content is empty : '.$type);
      return;
    }

    // optimize file with the provided driver
    $driver = $this->getDriver($type);
    
    $driver->processFile($path, true);
    $results = $driver->getResults();
    

    $this->logSection('file+', sprintf(' > %s', $force_name_to ? $force_name_to : $this->view_handler->getCombinedName($type, $combine->getFiles())));
    
    $this->logSection('optimize', sprintf(' > from %.2fKB to %.2fKB, ratio -%s%%', 
      $results['originalSize'] / 1024, 
      $results['optimizedSize'] / 1024, 
      100 - $results['ratio']
    ));
  }
  
  /**
   * 
   * @param string $type name associated to the driver : javascript or stylesheet
   * @param swDriveBase 
   */
  public function getDriver($type)
  {
    $configuration = $this->view_parameters->get('configuration');
    $driver =  $configuration[$type]['driver'];
    
    if(!class_exists($driver))
    {
      
      throw new sfException('Invalid driver class : '.$driver);
    }
    
    return new $driver;
  }
  
  /**
   * save the combined result into one file
   *
   * @param string $filename the filename to use to save the contentss
   * @param string $content the contents
   */
  public function saveContents($filename, $contents)
  {
    $path = dirname($filename);
    
    if(!is_dir($path) && !@mkdir($path, 0755, true))
    {
      
      throw new sfException(sprintf('The folder %s cannot be created', $path));
    }

    if(!is_writable($path))
    {
      
      throw new sfException(sprintf('The folder %s is not writable', $path));
    }
    
    if(!$contents)
    {
      
      return false;
    }
    
    if(!file_put_contents($filename, $contents))
    {
      
      throw new sfException('Unable to put the content of file : '.$filename);
    }
    
    return true;
  }
  
}