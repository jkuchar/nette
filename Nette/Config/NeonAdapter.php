<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 * Copyright (c) 2004, 2011 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Nette\Config;

use Nette,
	Nette\Utils\Neon;



/**
 * Reading and writing INI files.
 *
 * @author     Ondrej Hubsch
 */
final class NeonAdapter implements IAdapter
{
	/** @var string  section inheriting separator (section < parent) */
	public static $sectionSeparator = ' < ';

	/** @var string  key nesting separator (key1> key2> key3) */
	public static $keySeparator = '.';


	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new Nette\StaticClassException;
	}



	/**
	 * Reads configuration from NEON file.
	 * @param  string  file name
	 * @return array
	 * @throws Nette\InvalidStateException
	 */
	public static function load($file)
	{
		if (!is_file($file) || !is_readable($file)) {
			throw new Nette\FileNotFoundException("File '$file' is missing or is not readable.");
		}

		$neon = Neon::decode(file_get_contents($file));

		$separator = trim(self::$sectionSeparator);
		$data = array();
		foreach ($neon as $secName => $secData) {
			if ($secData === NULL) { // empty section
				$secData = array();
			}

			if (is_array($secData)) {
				// process extends sections like [staging < production]
				$parts = $separator ? explode($separator, $secName) : array($secName);

				if (count($parts) > 1) {
					$parent = trim($parts[1]);
					$cursor = & $data;

					foreach (self::$keySeparator ? explode(self::$keySeparator, $parent) : array($parent) as $part) {
						if (isset($cursor[$part]) && is_array($cursor[$part])) {
							$cursor = & $cursor[$part];
						} else {
							throw new Nette\InvalidStateException("Missing parent section $parent in '$file'.");
						}
					}
					$secData = Nette\ArrayUtils::mergeTree($secData, $cursor);
				}

				$secName = trim($parts[0]);
				if ($secName === '') {
					throw new Nette\InvalidStateException("Invalid empty section name in '$file'.");
				}
			}

			if (self::$keySeparator) {
				$cursor = & $data;
				foreach (explode(self::$keySeparator, $secName) as $part) {
					if (!isset($cursor[$part]) || is_array($cursor[$part])) {
						$cursor = & $cursor[$part];
					} else {
						throw new Nette\InvalidStateException("Invalid section [$secName] in '$file'.");
					}
				}
			} else {
				$cursor = & $data[$secName];
			}

			if (is_array($secData) && is_array($cursor)) {
				$secData = Nette\ArrayUtils::mergeTree($secData, $cursor);
			}

			$cursor = $secData;
		}

		return $data;
	}



	/**
	 * Write NEON file.
	 * @param  Config to save
	 * @param  string  file
	 * @return void
	 */
	public static function save($config, $file)
	{
		if (!file_put_contents($file, "# generated by Nette\n\n" . Neon::encode($config, Neon::BLOCK))) {
			throw new Nette\IOException("Cannot write file '$file'.");
		}
	}

}
