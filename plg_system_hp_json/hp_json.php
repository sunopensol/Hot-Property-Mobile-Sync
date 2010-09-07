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
define('DEBUG', false);
ini_set('display_errors', 0);
define('ERROR_LOG', '/var/www/joomla3/logs/error.log');
jimport('joomla.application.menu');
jimport( 'joomla.plugin.plugin' );

class plgSystemHp_json extends JPlugin
{

	var $_db = null;

	/**
	 * Constructor
	 * @access	protected
	 * @param	object	$subject The object to observe
	 * @param 	array   $config  An array that holds the plugin configuration
	 * @since	1.0
	 */
	function plgSystemHp_json(& $subject, $config)
	{
		$this->_db = JFactory::getDBO();
		
		parent :: __construct($subject, $config);
	}

	function onAfterInitialise()
	{
		global $mainframe;
		if ($mainframe->isAdmin()) {
			return; // Dont run in admin
		}
		/* determine if this plugin needs to run */
		if (!JRequest::getBool('hpjson', '', 'GET')) {
			return;
		}

		global $mainframe;
		/* make sure the mosets plugin is enabled / installed */
		if (JPluginHelper::isEnabled('mosets', 'framework')) {
			JPluginHelper::importPlugin('mosets', 'framework');
		} else {
			JError::raiseError(404, 'Mosets Framework plugin is required for this plugin. Please install and enable it.');
		}
		/* do some initialization */
		$mainframe->triggerEvent('onInitializeMosetsFramework');
		$hotproperty =& MosetsFactory::getApplication('hotproperty');		
		$db			=& MosetsFactory::getDBO();
		$nullDate	= $db->getNullDate(); 
		$now		= JFactory::getDate();
		
		/* what is your desire, o master ? */
		$cmd = JRequest::getCmd('command', '', 'GET');

		switch ($cmd) {
			/* login, using username and password 
			** query string: index.php?hpjson=1&command=ss_login
			*/
		case 'ss_login' :
			$username = JRequest::getString('username', '', 'POST');
			$password = JRequest::getString('password', '', 'POST');
			if (DEBUG) {
				error_log("username: $username\tpassword: $password\n", 3, ERROR_LOG);
			}
			if (!$username || !$password) {
				// send error message
				exit;
			}
			if ($mainframe->login(array('username' => $username,
										'password' => $password))) {
				// get cookie name and value from session
				$user	=& JFactory::getUser();
				echo $user->id;
				
			} else {
				echo "0";
			}
			exit;
			break;

		case 'ss_login_get' :
			$username = JRequest::getString('username', '', 'GET');
			$password = JRequest::getString('password', '', 'GET');
			//error_/log("username: $username\tpassword: $password\n", 3, ERROR_LOG);
			if (!$username || !$password) {
				// send error message
				exit;
			}
			if ($mainframe->login(array('username' => $username,
										'password' => $password))) {
				// get cookie name and value from session
				$user	=& JFactory::getUser();
				echo $user->id;
				
			} else {
				echo "0";
			}
			exit;
			break;
			/* sync properties, based on a timestamp sent, and agent id from session user 
			** query string: index.php?hpjson=1&command=ss_sync_properties
			** POST vales: modifiedProperties => JSON string representing an array of properties
			               deviceNow => current unix timestamp from device
						   lastSync => unix timestamp of last sync

			*/
		case 'ss_sync_properties' :
			require_once(dirname(__FILE__).DS.'hp_json'.DS.'sync.php');
			$user	=& JFactory::getUser();
			$agentObj = $hotproperty->getModel('Agent');
			
			if ($agent = $agentObj->getData('first', array('where' => array('Agent.user' => $user->id)))) {
				
				$lastSync = JRequest::getVar('lastSync', 0, 'POST');
				$changedPropertiesJSON = JRequest::getVar('modifiedProperties', '', 'POST');
				$deviceNow = JRequest::getVar('deviceNow', 0, 'POST');
				$offset = time() - $deviceNow;
				$deviceProps = array();
				if ($changedPropertiesJSON) {
					$deviceProps = json_decode($changedPropertiesJSON);
				}
				$sync = new SunSolSync($db, $hotproperty);

				//error_/log("lastSync: $lastSync\toffset: $offset\n", 3, ERROR_LOG);
				$returnProps = $sync->syncProperties($deviceProps, $agent, $lastSync,$offset);
				//	//error_/log(print_r($returnProps,true), 3, ERROR_LOG);
				echo json_encode($returnProps); // this is now a tuple, with the first element being the list of all ids, the second the returned properties
																	   
			} else {
				
				echo "0";
			}
			exit;
			break;
			
			/* get property types
			** query string: index.php?hpjson=1&command=ss_get_types
			** used on app initialization and thereafter on demand
			** returns a json string representing an array of types
			** returns 0 on failure
			*/
		case 'ss_get_types' :
			$model = $hotproperty->getModel('Type');
			if ($types = $model->getData('all',array())) {
				echo json_encode($types);
			} else {
				echo "0";
			}
			exit;
			break;
			
			/* get featured properties
			** query string: index.php?hpjson=1&command=featured
			** This should probably be pulled from RSS if we can include lat/lng
			*/
			/*
		case 'featured' : // get featured properties
			$model = $hotproperty->getModel('Property');
			$properties = $model->getData('all', array(
													   'where'	=> array(
																		 'Property.featured' => 1,
																		 'Property.approved' => 1,
																		 'Property.published' => 1,
																		 array(
																			   'OR' => array(
																							 'Property.publish_up' => $nullDate,
																							 'Property.publish_up <=' => $now->toMySQL()
																							 )
																			   ),
																		 array(
																			   'OR' => array(
																							 'Property.publish_down' => $nullDate,
																							 'Property.publish_down >=' => $now->toMySQL()
																							 )
																			   )
																		 ),
													   'contain' => array(
																		  'Featured' => array(),
																		  'PropertyField' => array(),
																		  'Photo' => array(
																						   'order' => array('Photo.ordering' => 'ASC')
																						   ),
																		  'Agent' => array(
																						   'contain' => array(
																											  'Company' => array()
																											  )
																						   ),
																		  'Type' => array()
																		  ),
													   'order' => array($hotproperty->getCfg('ordering') => $hotproperty->getCfg('ordering_dir')),
													   'limitstart' => 0,
													   'limit' => $hotproperty->getCfg('limit')
													   ));
		$total = $model->getTotal();
		echo json_encode($properties);

		$model = $hotproperty->getModel('Field');
		$extrafields = $model->getData('all', array(
													'where' => array(
																	 'Field.name NOT IN' => array('name', 'full_text', 'featured'), // Disable 'name', 'full_text' and 'featured'
																	 'Field.published' => 1,
																	 'Field.hidden' => 0,
																	 'Field.featured' => 1
																	 ),	
													'order' => array('Field.ordering' => 'ASC')
													));
		echo json_encode($extrafields);
		break;
			*/
		case 'ss_sync_photos' :
			require_once(dirname(__FILE__).DS.'hp_json'.DS.'sync.php');
			$user	=& JFactory::getUser();
			//error_/log("user: " . print_r($user,true), 3, ERROR_LOG);
			//error_/log("in ss_sync_photos\n", 3, ERROR_LOG);
			$agentObj = $hotproperty->getModel('Agent');
			
			if ($agent = $agentObj->getData('first', array('where' => array('Agent.user' => $user->id)))) {
							
				$changedPhotosJSON = JRequest::getVar('photos', array(), 'POST');
				$files = $_FILES;
				//error_/log("FILES: " . print_r($files, true),3, ERROR_LOG);
				$devicePhotos = array();
				if ($changedPhotosJSON) {
					$devicePhotos = json_decode($changedPhotosJSON);
				}
				for ($i = 0; $i < sizeof($devicePhotos); $i++) {
					if (isset($files['original_' . $i])) {
						$devicePhotos[$i]->files[] = array('original' => $files['original_' . $i]);
					}
				}
				//error_/log("devicePhotos: " . print_r($devicePhotos,true), 3, ERROR_LOG);
				$sync = new SunSolSync($db, $hotproperty);
				$returnPhotos = $sync->syncPhotos($devicePhotos,$agent);
				//error_/log("returnphotos: " . print_r($returnPhotos,true), 3, ERROR_LOG);
			
				echo json_encode($returnPhotos);
			} else {
				echo "0";
			}
			exit;
			break;


			/*
		  **** kml
		  **** returns a kml document with all published docs as markers
		  **** 
		  */
		case 'kml' :

			

			
			// Creates an array of strings to hold the lines of the KML file.
			$kml = array('<?xml version="1.0" encoding="UTF-8"?>');
			$kml[] = '<kml xmlns="http://earth.google.com/kml/2.1">';
			$kml[] = ' <Document>';
			$kml[] = ' <Style id="realestateStyle">';
			$kml[] = ' <IconStyle id="realestateIcon">';
			$kml[] = ' <Icon>';
			$kml[] = ' <href>http://google-maps-icons.googlecode.com/files/realestate.png</href>';
			$kml[] = ' </Icon>';
			$kml[] = ' </IconStyle>';
			$kml[] = ' </Style>';




			$now		= JFactory::getDate();
			$model = $hotproperty->getModel('Property');
			$returnPropertiesAll = $model->getData('all', array(
														   'where' => array(
																			
																			'Property.approved' => 1,
																			'Property.published' => 1,
																			array(
																				  'OR' => array(
																								'Property.publish_up' => $nullDate,
																								'Property.publish_up <=' => $now->toMySQL()
																								)
																				  ),
																			array(
																				  'OR' => array(
																								'Property.publish_down' => $nullDate,
																								'Property.publish_down >=' => $now->toMySQL()
																								)
																				  )
																			),
														   'contain' => array(
																			  
																			  'PropertyField' => array(),
																			  'Photo' => array(
																							   'limit' => 1,
																							   'order' => array('Photo.ordering' => 'ASC')
																							   )
																			  ),
														   'order' => ''
														   
														   ));
			// Iterates through the MySQL results, creating one Placemark for each row.
			//print_r($returnPropertiesAll);
			$fModel = $hotproperty->getModel('Field');
			$fTable = $fModel->getTable();
			
			$fields = $fModel->getData('all', array('where' => array('iscore' => 0)));
			$fieldData = array();
			foreach ($fields as $field) {
				$fieldData[$field->name] = $field->id;
			}

			foreach ($returnPropertiesAll as $prop)
				{

					
					// check first that we have lat and lng
					$lat = $lng = 0;
					foreach ($prop->PropertyField as $retPf) {
						if ($retPf->field == $fieldData['ss_latitude']) {
							$lat = $retPf->value;
						} else if ($retPf->field == $fieldData['ss_longitude']) {
							$lng = $retPf->value;
						}
					}
					if ($lat == 0) {
						continue;
					}
					// Creates a Placemark and append it to the Document.
					$kml[] = ' <Placemark id="placemark' . $prop->id . '">';
					$kml[] = ' <name>' . htmlentities($prop->name) . '</name>';
					$kml[] = ' <description><![CDATA[' .$prop->intro_text;
					$kml[] = '<a href="/index.php?option=com_hotproperty&view=properties&layout=property&id=' . $prop->id . '&Itemid=1">View Details</a>]]></description>';
					$kml[] = ' <styleUrl>#realestateStyle</styleUrl>';
					$kml[] = ' <Point>';
					$kml[] = ' <coordinates>' . $lng . ','  . $lat . '</coordinates>';
					$kml[] = ' </Point>';
					$kml[] = ' </Placemark>';


				} 
			
			// End XML file
			$kml[] = ' </Document>';
			$kml[] = '</kml>';
			$kmlOutput = join("\n", $kml);
			header('Content-type: application/vnd.google-earth.kml+xml');
			echo $kmlOutput;
			
			exit;
			break;


				/*
			  **** kml_single
			  **** returns a kml document with a single published property as marker
			  **** 
			  */
			case 'kml_single' :



				$propId = JRequest::getVar('propId', '', 'GET');

				// Creates an array of strings to hold the lines of the KML file.
				$kml = array('<?xml version="1.0" encoding="UTF-8"?>');
				$kml[] = '<kml xmlns="http://earth.google.com/kml/2.1">';
				$kml[] = ' <Document>';
				$kml[] = ' <Style id="realestateStyle">';
				$kml[] = ' <IconStyle id="realestateIcon">';
				$kml[] = ' <Icon>';
				$kml[] = ' <href>http://google-maps-icons.googlecode.com/files/realestate.png</href>';
				$kml[] = ' </Icon>';
				$kml[] = ' </IconStyle>';
				$kml[] = ' </Style>';




				$now		= JFactory::getDate();
				$model = $hotproperty->getModel('Property');
				$prop = $model->getData('first', array(
															   'where' => array(

																				'id' => $propId
																				),
															   'contain' => array(

																				  'PropertyField' => array(),
																				  'Photo' => array(
																								   'limit' => 1,
																								   'order' => array('Photo.ordering' => 'ASC')
																								   )
																				  ),
															   'order' => ''

															   ));

				// Iterates through the MySQL results, creating one Placemark for each row.
				$fModel = $hotproperty->getModel('Field');
				$fTable = $fModel->getTable();

				$fields = $fModel->getData('all', array('where' => array('iscore' => 0)));
				$fieldData = array();
				foreach ($fields as $field) {
					$fieldData[$field->name] = $field->id;
				}


						// check first that we have lat and lng
				$lat = $lng = 0;
				foreach ($prop->PropertyField as $retPf) {
					if ($retPf->field == $fieldData['ss_latitude']) {
						$lat = $retPf->value;
					} else if ($retPf->field == $fieldData['ss_longitude']) {
						$lng = $retPf->value;
					}
				}
				if ($lat == 0) {
					continue;
				}
				// Creates a Placemark and append it to the Document.
				$kml[] = ' <Placemark id="placemark' . $prop->id . '">';
				$kml[] = ' <name>' . htmlentities($prop->name) . '</name>';
				$kml[] = ' <description><![CDATA[' .$prop->intro_text;
				$kml[] = '<a href="/index.php?option=com_hotproperty&view=properties&layout=property&id=' . $prop->id . '&Itemid=1">View Details</a>]]></description>';
				$kml[] = ' <styleUrl>#realestateStyle</styleUrl>';
				$kml[] = ' <Point>';
				$kml[] = ' <coordinates>' . $lng . ','  . $lat . '</coordinates>';
				$kml[] = ' </Point>';
				$kml[] = ' </Placemark>';


				// End XML file
				$kml[] = ' </Document>';
				$kml[] = '</kml>';
				$kmlOutput = join("\n", $kml);
				header('Content-type: application/vnd.google-earth.kml+xml');
				echo $kmlOutput;

				exit;
				break;

		/*
		  case 'search' :
		  $search_fields = JRequest::getVar('Field', array(), 'get', 'array');
		  $model = $hotproperty->getModel('Property');
		  if ($results_ids = $model->search($search_fields)) {
				$db			=& MosetsFactory::getDBO();
				$nullDate	= $db->getNullDate(); 
				$now		= JFactory::getDate();
				
				$properties = $model->getData('all', array(
														   'where' => array(
																			'Property.id' => $results_ids,
																			'Property.approved' => 1,
																			'Property.published' => 1,
																			array(
																				  'OR' => array(
																								'Property.publish_up' => $nullDate,
																								'Property.publish_up <=' => $now->toMySQL()
																								)
																				  ),
																			array(
																				  'OR' => array(
																								'Property.publish_down' => $nullDate,
																								'Property.publish_down >=' => $now->toMySQL()
																								)
																				  )
																			),
														   'contain' => array(
																			  'Agent' => array(
																							   'contain' => array(
																												  'Company' => array()
																												  )
																							   ),
																			  'PropertyField' => array(),
																			  'Photo' => array(
																							   'limit' => 1,
																							   'order' => array('Photo.ordering' => 'ASC')
																							   )
																			  ),
														   'order' => '',
														   'limitstart' => $this->get('limitstart'),
														   'limit' => $this->get('limit')
														   ));
				$total =  $model->getTotal();
				echo json_encode($properties);
				exit;

			}
			exit;
			break;
			*/
		}
	}
}
?>
