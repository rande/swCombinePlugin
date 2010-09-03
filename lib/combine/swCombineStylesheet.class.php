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
class swCombineStylesheet extends swCombineBase
{
  
  protected
    $paths = array(),
    $path_pos = -1;
 
  
  // fix import
  public function getContents($asset)
  {
    // reset state variables
    $this->path_pos = -1;
    $this->paths    = array();
    
    // import css from external declarations
    $contents = $this->fixImportStatement($asset);
    
    // get the version so each time the cache is cleared then the image are reload 
    // from the webserver
    $params  = sfConfig::get('app_swToolbox_swCombine', array('version' => false));
    $version = $params['version'];
    if($version)
    {
      $pattern = '/url\(("|\'|)(.*)("|\'|)\)/smU';
      $replacement = "url(\${2}?_sw=${version})";

      $contents = preg_replace($pattern, $replacement, $contents, -1);
    }
    
    // remove the '@CHARSET UTF-8'
    $charset_pattern = '/@charset ([^;]*);/i';
    $contents = preg_replace($charset_pattern, '', $contents);
    
    return $contents;
  }
  
  public function fixImportStatement($asset)
  {
    $contents = @file_get_contents($asset);
    
    if(!$contents)
    {
      throw new Exception('unable to read the asset : '.$asset);
    }
    
    // remove BOM
    $contents = $this->removeBom($contents);
    
    $asset = realpath($asset);
    $info = pathinfo($asset);
    
    $this->path_pos++;

    // store the current path in the recursion
    $this->paths[$this->path_pos] = $info['dirname'];
    
    // fix image path
    $fix = preg_replace_callback('/url\(("|\'|)(.*)("|\'|)\)/smU', array($this, 'fixImportImageCallback'), $contents);
    
    if($fix)
    {
      $contents = $fix;
    }
    
    // fetch the contents of any include files
    $fix = preg_replace_callback('/@import url\([ ]*[\'|"](.*)[\'|"][ ]*\);/smU', array($this, 'fixImportStatementCallback'), $contents);

    if($fix)
    {
      $contents = $fix;
    }
    
    $this->path_pos--;
        
    return $contents;
  }
  
  public function fixImportImageCallback($matches)
  {
    // have to find a better regular expression
    // ignore @import statements as there are handled by fixImportStatementCallback
    if(preg_match('/\.css/', $matches[2]))
    {
      
      return $matches[0];
    }
    
    // fix image path 
    if($matches[2]{0} == '/')
    {
      $file = $matches[2];
    }
    else
    {
      $config_handler = sfYaml::load(sfConfig::get('sf_app_config_dir') . '/config_handlers.yml');
      $configuration = @$config_handler['modules/*/config/view.yml']['param']['configuration'];

      $css_dir = sfConfig::get('sf_web_dir') . "/css";
      $css_private_path = @$configuration['javascript']['private_path'];
      $path_parts = preg_split('/\%/', $css_private_path);
      foreach($path_parts as $part){
          $configVar = sfConfig::get(strtolower($part));
          if(!empty($configVar))
            $css_path .= $configVar;
          else
            $css_path .= $part;
      }

      if(is_dir($css_path)){
          $css_dir = $css_path;
      }

      $file = $this->paths[$this->path_pos].'/'.$matches[2];
      $file = realpath($file);

      $file = $this->getRelativePath($file, $css_dir);
      //$file = str_replace($web_dir, '', $file);
    }
    
    if($file)
    {
      return 'url('.$file.')';
    }
    
    $this->logSection('fix-image', 'unable to find the file : '.$matches[2]);
    
    return 'none';
  }
  
  /**
   * This might have other issue with related pictures
   */
  public function fixImportStatementCallback($matches)
  {
    $infos = $this->paths[$this->path_pos];

    $file = $this->paths[$this->path_pos].'/'.$matches[1];
    
    $content = @file_get_contents($file);

    if($content)
    {
      
      return $this->fixImportStatement($file, $content);
    }
    
    throw new sfException('Unable to import statement file : '.$file);
  }


  /**
   * Returns a relative path 
   */
  protected function getRelativePath( $path, $compareTo ) {
    // clean arguments by removing trailing and prefixing slashes
    if ( substr( $path, -1 ) == '/' ) {
      $path = substr( $path, 0, -1 );
    }
    if ( substr( $path, 0, 1 ) == '/' ) {
      $path = substr( $path, 1 );
    }

    if ( substr( $compareTo, -1 ) == '/' ) {
      $compareTo = substr( $compareTo, 0, -1 );
    }
    if ( substr( $compareTo, 0, 1 ) == '/' ) {
      $compareTo = substr( $compareTo, 1 );
    }

    // simple case: $compareTo is in $path
    if ( strpos( $path, $compareTo ) === 0 ) {
      $offset = strlen( $compareTo ) + 1;
      return substr( $path, $offset );
    }

    $relative  = array(  );
    $pathParts = explode( '/', $path );
    $compareToParts = explode( '/', $compareTo );

    foreach( $compareToParts as $index => $part ) {
      if ( isset( $pathParts[$index] ) && $pathParts[$index] == $part ) {
        continue;
      }

      $relative[] = '..';
    }

    foreach( $pathParts as $index => $part ) {
      if ( isset( $compareToParts[$index] ) && $compareToParts[$index] == $part ) {
        continue;
      }

      $relative[] = $part;
    }

    return implode( '/', $relative );
  }
}
