<?php

/**
* @version		$Id$
* @package		HP_JSON Plugin
* @author       Tom Lancaster <tom@sunopensol.com>
* @copyright	Copyright (C) 2010 Sunshine Open Solutions. All rights reserved.
* @license		GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.menu');

class SunSolSync {
	
	private $db;
	private $hotproperty;
	/**
	 * Constructor
	 *
	 * @access protected
	 * @param	An optional associative array of configuration settings.
	 * @return	void
	 */
	function __construct($db, $hotproperty) {
		$this->db = $db;
		$this->hotproperty = $hotproperty;
		
	}

	/**
	 * syncProperties
	 *
	 * @access public
	 * @param	An array of changed / new property objects from the device.
	 * @return	An array of novel or modified property objects, with changes merged in
	 */
	public function syncProperties($deviceProperties, $agent, $lastSync, $offset) {
		//echo "in syncProperties<br/>";
		$db =& JFactory::getDBO();

		////error_/log("deviceProps: " . print_r($deviceProperties,true), 3, ERROR_LOG);
		$newProperties = $changedProperties = array();
		foreach ($deviceProperties as $deviceProperty) {
			if (DEBUG) {
				error_log("price: " . $deviceProperty->price . "\n", 3, ERROR_LOG);
			}
			$deviceProperty->price = ($deviceProperty->price + 0);
			$deviceProperty->approved = 1;
			if (is_numeric($deviceProperty->id) && $deviceProperty->id > 0) {
			
				$changedProperties[] = $deviceProperty;
			} else {
				$newProperties[] = $deviceProperty;
			}
		}

		////error_/log("new Props: " . print_r($newProperties,true), 3, ERROR_LOG);
		/* must query the db to find out ids of required extra fields */
		$fModel = $this->hotproperty->getModel('Field');
		$fTable = $fModel->getTable();

		$fields = $fModel->getData('all', array('where' => array('iscore' => 0)));
		$fieldData = array();
		foreach ($fields as $field) {
			$fieldData[$field->name] = $field->id;
		}

		/* add the new properties */
		$model = $this->hotproperty->getModel('Property');
		$propertyTable = $model->getTable();
		
		$counter = 1;
		foreach ($newProperties as $newProperty) {
			$newProperty->created = date("Y-m-d H:i:s", $newProperty->created);
			$newProperty->modified = date("Y-m-d H:i:s");
			$newProperty->agent = $agent->id;
			$newProperty->approved = 1;
			if (!$pKey = $propertyTable->save(-$counter, $newProperty)) {
				
				////error_/log( "error saving property: " . print_r($model->getError(),true) . "\n", 3, ERROR_LOG);
			} else {
				/* now save Property Fields */
			
				foreach ($fieldData as $name => $key) {
					if (isset($newProperty->$name)) {
						$sql = "INSERT INTO #__hp_properties2 (field, property, value) values (" . $db->quote($key) . "," . $db->quote($pKey->id) . ","  . $db->quote($newProperty->$name) . ")";
					
						////error_/log("new property field sql: $sql\n", 3 ,ERROR_LOG);
						$db->setQuery($sql);
						$db->query();
					}
				}


				

				////error_/log( "property saved\n", 3, ERROR_LOG);
			}
			$counter++;
		}
		// all this math is done in utc */
		//date_default_timezone_set('UTC');
		$now		= JFactory::getDate();
		$fieldsKeysNames = array_flip($fieldData);
		foreach ($changedProperties as $changedProperty) {
			
			if ($dbProperty = $model->getData('first', array( 'where' => array('agent' => $agent->id,
																			   'id' => $changedProperty->id),
															  'contain' => array('PropertyField' => array())))) {
				//$changedProperty->publish_up = $dbProperty->publish_up;
				//$changedProperty->publish_down = $dbProperty->publish_down;
				if (DEBUG) {
					error_log("db modified minus device modified: " . (strtotime($dbProperty->modified) - $changedProperty->modified) . "\toffset: $offset\n", 3, ERROR_LOG);
				
					error_log("db modified: " . $dbProperty->modified . " device modified: " . date("Y-m-d H:i:s", $changedProperty->modified) . "\n", 3, ERROR_LOG);
				}
				if ((strtotime($dbProperty->modified) + $offset ) >  $changedProperty->modified) {
					if (DEBUG) {
						error_log("db is more recent for property: " . $changedProperty->name . "\n", 3, ERROR_LOG);
					}
					$changed = false;
					// db is more recent
					/* for changes in display status, the device has precedent, whoever has changed more recently */
					foreach (array('published', 'featured', 'approved') as $status) {
						if ($changedProperty->$status != $dbProperty->$status) {
							if (DEBUG) {
								error_log("changed status\n", 3, ERROR_LOG);
							}
							if ($status == 'featured') {
								$model->feature($changedProperty->id, $changedProperty->featured);
							} else {
								$dbProperty->$status = $changedProperty->$status;
								$changed = true;
							}
							
						}
					}
					/* save the db model if changed */
					if ($changed) {
						
						if (!$model->save(array('Property' => array($dbProperty->id => $dbProperty)))) {
							if (DEBUG) {
								error_log( "error saving back db property\n", 3, ERROR_LOG);
							}
						}
					}
				} elseif ((strtotime($dbProperty->modified) + $offset) < $changedProperty->modified) {
					/* for other changes, the more recent has precedence */
					if (DEBUG) {
						error_log("device has more recent changes for property: " . $changedProperty->name . "\n", 3, ERROR_LOG);
					}


					
					if ($propertyField = $dbProperty->PropertyField) {
						foreach ($propertyField as $pf) {
							$pf->value = $changedProperty->$fieldsKeysNames[$pf->field];
						}

					} else {
						foreach ($fieldData as $name => $key) {
							if (isset($changedProperty->$name)) {
								$propertyField[] = (object)array('id' => -1,
																 'property' => $dbProperty->id,
																 'field' => $key,
																 'value' => $changedProperty->$name
																 );
							}
						}
						
					}
					if (DEBUG) {
						error_log("property fields: " . print_r($propertyField,true), 3, ERROR_LOG);
					}
					$changedProperty->modified = date("Y-m-d H:i:s", $changedProperty->modified - $offset); // convert to mysql time
					$changedProperty->created = $dbProperty->created;
					$changedProperty->agent = $agent->id;
					if (DEBUG) {
						error_log("changedProp: " . print_r($changedProperty,true), 3 ,ERROR_LOG);
					}
					if ($model->save(array('Property' => array($changedProperty->id => $changedProperty)))) {
						/* save PropertyFields */
						foreach ($propertyField as $pf) {
							if ($pf->id > 0) {
								$sql = "UPDATE #__hp_properties2 set value = " . $db->quote($pf->value) . " where id = " . $db->quote($pf->id);

							} else {
								$sql = "INSERT INTO #__hp_properties2 (field, property, value) values (" . $db->quote($pf->field) . "," . $db->quote($pf->property) . ","  . $db->quote($pf->value) . ")";
							}
							$db->setQuery($sql);
							if (DEBUG) {
								error_log("sql: $sql\n", 3, ERROR_LOG);
							}
							if (!$db->query()) {
								////error_/log("error from sql: $sql\n", 3, ERROR_LOG);
							}
						}
						if (DEBUG) {
							error_log( "saved modified property\n", 3, ERROR_LOG);
						}
					} else {
						if (DEBUG) {
							error_log("error saving modified property\n", 3, ERROR_LOG);
						}
					}

				}
			}  else {
				if (DEBUG) {
					error_log( "that property has been deleted from the web database\n", 3, ERROR_LOG);
				}
			}
		}
		

		$returnPropertiesAll = $model->getData('all', array('where' => array('agent' => $agent->id),
															'contain' => array(
																			   'PropertyField' => array()
																			   )
															));
		$returnProperties = $returnPropIds = array();
		foreach ($returnPropertiesAll as $retProp) {
			$returnPropIds[] = $retProp->id;
			if (strtotime($retProp->modified) >=  date("Y\-m\-d H:i:s",$lastSync + $offset)) {
				$retProp->modified = strtotime($retProp->modified) - $offset;
				$retProp->created = strtotime($retProp->created) - $offset;
				//$retProp->publish_up = strtotime($retProp->publish_up) - $offset;
				//$retProp->publish_down = strtotime($retProp->publish_down) - $offset;
				if (isset($retProp->PropertyField) && $retProp->PropertyField) {
					////error_/log("pf: " . print_r($retProp->PropertyField,true), 3 ,ERROR_LOG);
					foreach ($retProp->PropertyField as $retPf) {
						////error_/log("fkn: " . print_r($fieldsKeysNames,true), 3, ERROR_LOG);
						$retProp->$fieldsKeysNames[$retPf->field] = $retPf->value;
					}
					unset($retProp->PropertyField);
				}
				$returnProperties[] = $retProp;

			}
		}
		if (DEBUG) {
			error_log("returnProps: " . print_r($returnProperties,true), 3, ERROR_LOG);
		}
		return array($returnPropIds,$returnProperties);

	}

	/**
	 * syncPhotos
	 *
	 * @access public
	 * @param	An array of changed / new photo objects from the device.
	 * @param   An Agent object
	 * @return	An array of novel or modified photo objects, with changes merged in
	 */

	public function syncPhotos($devicePhotos, $agent) {
		$deletedPhotos = $newPhotos = $updatedPhotos = array();
		////error_/log("in SyncPhotos\n" , 3, ERROR_LOG);
		foreach ($devicePhotos as $devicePhoto) {
			if ($devicePhoto->deleted == 1 && $devicePhoto->id) {
				$deletedPhotos[] = $devicePhoto;
			} elseif (!$devicePhoto->id) {
				$newPhotos[] = $devicePhoto;
			} else {
				$updatedPhotos[] = $devicePhoto;
			}
		}
	   
		$model = $this->hotproperty->getModel('Photo');
		$pModel = $this->hotproperty->getModel('Property');
		// deal with deleted pix first
		foreach ($deletedPhotos as $deletedPhoto) {
			if ($dbProperty = $pModel->getData('first', array( 'where' => array('agent' => $agent->id,
																				'id' => $deletedPhoto->property)))) {
				////error_/log("removing photo: " . $deletedPhoto->id . "\n", 3, ERROR_LOG);
				$model->remove(array($deletedPhoto->id));
			}
		}

		// now do the new pix; they have files['original']
		foreach ($newPhotos as $newPhoto) {
			////error_/log("adding new photo: " . print_r($newPhoto,true), 3, ERROR_LOG);
			$counter = 1;
			$filesArray = array();
			if (isset($newPhoto->files)) {
				foreach ($newPhoto->files as $file) {
					$filesArray['Photo'][-$counter] = $file;
					$counter++;
				} 
			}
			////error_/log("filesArray: " . print_r($filesArray, true), 3, ERROR_LOG);
			if ($model->save(array('Photo' => array(-1 => $newPhoto)), $filesArray)) {
				////error_/log("successfully saved new photo\n", 3, ERROR_LOG); 
			} else {
				////error_/log("save new photo not successful: " . print_r($model->getError(),true), 3, ERROR_LOG);
			} 
		}

		// do the changed pix
		foreach ($updatedPhotos as $updatedPhoto) {
			if ($dbProperty = $pModel->getData('first', array( 'where' => array('agent' => $agent->id,
																				'id' => $updatedPhoto->property)))) {
				////error_/log("updaing photo: " . print_r($updatedPhoto,true), 3, ERROR_LOG);
				if ($dbPhoto = $model->getData('first', array('where' => array('id' => $updatedPhoto->id)))) {
					$dbPhoto->title = $updatedPhoto->title;
					$dbPhoto->desc = $updatedPhoto->desc;
					$dbPhoto->ordering = $updatedPhoto->ordering;
					if (!$model->save(array('Photo' => array($dbPhoto->id => $dbPhoto)))) {
						//echo "unable to save updated photo\n";
					} else {
						////error_/log("successfully saved updated photo: " . $dbPhoto->id . "\n", 3, ERROR_LOG);
					}
				}
			}
		}
		

		$returnProps = $pModel->getData('all', array('where' => array('Property.agent' => $agent->id),
													  'contain' => array('Photo' => array(
																						   'order' => array('Photo.ordering' => 'ASC')
																						   ),
																		 )));
		$returnPhotos = array();
		foreach ($returnProps as $returnProp) {
			////error_/log("return Prop Photo: " . print_r($returnProp->Photo,true), 3, ERROR_LOG);
			if (isset($returnProp->Photo)) {
				$returnPhotos = array_merge($returnPhotos, $returnProp->Photo);
			}
		}
		////error_/log("returnPhotos: " . print_r($returnPhotos,true), 3, ERROR_LOG);
		return $returnPhotos;
		
	}
		
}
?>