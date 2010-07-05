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
abstract class swCombineBase 
{
  protected
    $dispatcher = null,
    $formatter  = null,
    $files      = array();
    
  public function __construct(sfEventDispatcher $dispatcher = null, sfFormatter $formatter = null)
  {
    
    $this->dispatcher = $dispatcher;
    $this->formatter = $formatter;
  }
  
  public function addFile($file)
  {
    $this->files[] = $file;
  }
  
  public function getFiles()
  {
    
    return $this->files;
  }
  
  public function getContentsFromFiles()
  {
    $contents = '';
    foreach($this->files as $file)
    {
      $file = sprintf('%s/%s', sfConfig::get('sf_web_dir'), $file);
      $contents .= $this->getContents($file)."\n";
    }
    
    return $contents;
  }

  /**
   *
   * remove BOM file which break IE rendering when the BOM is
   * found in the middle of a combined CSS file
   *
   * @param string $contents
   * @return string
   */
  public function removeBom($contents)
  {
    
    return str_replace(pack("CCC",0xef,0xbb,0xbf), '', $contents);
  }
  
  /**
   * Logs a message.
   *
   * @param mixed $messages  The message as an array of lines of a single string
   */
  public function log($messages)
  {
    if (!is_array($messages))
    {
      $messages = array($messages);
    }

    $this->dispatcher->notify(new sfEvent($this, 'command.log', $messages));
  }

  /**
   * Logs a message in a section.
   *
   * @param string  $section  The section name
   * @param string  $message  The message
   * @param int     $size     The maximum size of a line
   * @param string  $style    The color scheme to apply to the section string (INFO, ERROR, or COMMAND)
   */
  public function logSection($section, $message, $size = null, $style = 'INFO')
  {
    $this->dispatcher->notify(new sfEvent($this, 'command.log', array($this->formatter->formatSection($section, $message, $size, $style))));
  }
  
  abstract function getContents($filename);
}