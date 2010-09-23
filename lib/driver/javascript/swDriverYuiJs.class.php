<?php
/**
 * swCombinePlugin YUI javascript optimization driver
 *
 * @tutorial    http://developer.yahoo.com/yui/compressor/
 *
 * @package     swCombinePlugin
 * @subpackage  driver
 * @author      Harald Kirschner <harald [at] digitarald.com>
 *
 */
class swDriverYuiJs extends swDriverBase
{
	public function doProcessFile($file, $replace = false)
	{
		$cmd = sprintf('java -jar %s/swCombinePlugin/lib/vendor/yui/yuicompressor.jar --type js --charset utf-8 %s', sfConfig::get('sf_plugins_dir'), $file);

		$return = false;
		$output = array();
		exec($cmd, $output, $return);

		if($return != 0)
		{
			throw new RuntimeException('unable to compile the asset with yui compressor');
		}

		$optimizedContent = implode("\n", $output);

		if ($replace)
		{
			return parent::replaceFile($file, $optimizedContent);
		}
		else
		{
			return $optimizedContent;
		}
	}
}

