<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Daniel Alder <info@snowflake.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Plugin 'Snowbabel' for the 'Snowbabel' extension.
 *
 * @author	Daniel Alder <info@snowflake.ch>
 * @package	TYPO3
 * @subpackage	tx_snowbabel
 */
class tx_snowbabel_system_indexing extends tx_scheduler_Task {

	/**
	 * @var tx_snowbabel_Configuration
	 */
	private static $confObj;

	/**
	 * @var tx_snowbabel_system_translations
	 */
	private static $SystemTranslation;

	/**
	 * @var tx_snowbabel_system_statistics
	 */
	private static $SystemStatistic;

	/**
	 * @var tx_snowbabel_Db
	 */
	private static $Db;

	/**
	 * @var
	 */
	private static $CurrentTableId;

	/**
	 * @return void
	 */
	private static function init() {

			// Init Configuration
		self::initConfiguration();

			// Init System Translations
		self::initSystemTranslations();

			// Init System Statistics
			// TODO: not yet implemented
		self::initSystemStatistics();

	}

	/**
	 * @return bool
	 */
	public function execute() {

		self::init();

			// Get Current TableId & Negate
		self::$CurrentTableId = self::$Db->getCurrentTableId() ? 0 : 1;

			// Indexing Extensions
		self::indexingExtensions();

			// Indexing Files
		self::indexingFiles();

			// Indexing Labels
		self::indexingLabels();

			// Indexing Translations
		self::indexingTranslations();

			// Switch CurrentTableId
		self::$Db->setCurrentTableId(self::$CurrentTableId);

			// Add Scheduler Check To Localconf & Mark Configuration Changes As 'OK'
		self::$confObj->setSchedulerCheckAndChangedConfiguration();

		return true;
	}

	/**
	 * @return void
	 */
	private static function indexingExtensions() {

			// Get Extensions From Typo3
		$Extensions = self::$SystemTranslation->getExtensions();

			// Write Extensions To Database
		self::$Db->setExtensions($Extensions, self::$CurrentTableId);

	}

	/**
	 * @return void
	 */
	private static function indexingFiles() {

			// Get Extensions From Database
		$Extensions = self::$Db->getExtensions(self::$CurrentTableId);

			// Get Files From Typo3
		$Files = self::$SystemTranslation->getFiles($Extensions);

			// Write Extensions To Database
		self::$Db->setFiles($Files, self::$CurrentTableId);

	}

	/**
	 * @return void
	 */
	private static function indexingLabels() {

			// Get Files From Database
		$Files = self::$Db->getFiles(self::$CurrentTableId);

			// Get Labels From Typo
		$Labels = self::$SystemTranslation->getLabels($Files);

			// Write Labels To Database
		self::$Db->setLabels($Labels, self::$CurrentTableId);

	}

	/**
	 * @return void
	 */
	private function indexingTranslations() {

			// Important! Needed For Caching in getLabels
		$Conf['OrderBy'] = 'FileId';

			// Get Labels From Database
		$Labels = self::$Db->getLabels(self::$CurrentTableId, $Conf);

			// Get Translations From Typo
		$Translations = self::$SystemTranslation->getTranslations($Labels);

			// Write Translations To Database
		self::$Db->setTranslations($Translations, self::$CurrentTableId);

	}

	/**
	 * @return void
	 */
	private static function initConfiguration() {

		if (!is_object(self::$confObj) && !(self::$confObj instanceof tx_snowbabel_Configuration)) {
			self::$confObj = t3lib_div::makeInstance('tx_snowbabel_Configuration', array());

			self::$Db = self::$confObj->getDb();
		}

	}

	/**
	 * @return void
	 */
	private static function initSystemTranslations() {
		if (!is_object(self::$SystemTranslation) && !(self::$SystemTranslation instanceof tx_snowbabel_system_translations)) {
			self::$SystemTranslation = t3lib_div::makeInstance('tx_snowbabel_system_translations');
			self::$SystemTranslation->init(self::$confObj);
		}
	}

	/**
	 * @return void
	 */
	private static function initSystemStatistics() {
		if (!is_object(self::$SystemStatistic) && !(self::$SystemStatistic instanceof tx_snowbabel_system_statistics)) {
			self::$SystemStatistic = t3lib_div::makeInstance('tx_snowbabel_system_statistics');
			self::$SystemStatistic->init(self::$confObj);
		}
	}


	/**
	 * @var int
	 */
	private $performanceFirstTime = 0;

	/**
	 * @var int
	 */
	private $performanceLastTime = 0;

	/**
	 * @var int
	 */
	private $performanceLastMemory = 0;

	/**
	 * @return void
	 */
	private function startPerformance() {

			// Time
		$this->performanceLastTime = microtime(true);
		$this->performanceFirstTime = $this->performanceLastTime;

			// Memory
		$this->performanceLastMemory = memory_get_usage();

	}

	/**
	 * @static
	 * @param string $Key
	 * @return void
	 */
	private function logPerformance($Key) {

		// TIME

		$unit = ' micros';
		$current = microtime(true);

		$Time = array(
			'currentTime' => $current . $unit,
			'lastTime' => $this->performanceLastTime . $unit,
			'diffTime' => $current - $this->performanceLastTime . $unit,
			'sinceStart' => $current - $this->performanceFirstTime . $unit
		);

		$unit = ' byte';
		$this->performanceLastTime = $current;

		// MEMORY

		$current = memory_get_usage();

		$Memory = array(
			'currentMemory' => $current . $unit,
			'lastMemory' => $this->performanceLastMemory . $unit,
			'diffMemory' => $current - $this->performanceLastMemory . $unit
		);

		$this->performanceLastMemory = $current;


		t3lib_div::debug(array(
			'Time' => $Time,
			'Memory' => $Memory
		), $Key);
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/snowbabel/Classes/TCA/class.tx_snowbabel_system_indexing.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/snowbabel/Classes/TCA/class.tx_snowbabel_system_indexing.php']);
}

?>