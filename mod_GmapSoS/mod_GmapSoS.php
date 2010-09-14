<?php
/**
 * mod_GmapSoS module Entry Point
 *
 * @license        GNU/GPL, see LICENSE.php
 * mod_GoogleMap is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * 
 * This work is based on ModuleMap by Benjamin Haettasch
 * http://joomlacode.org/gf/project/mod_googlemap/
 */

defined ( '_JEXEC' ) or die ( 'Restricted access' );

//Get Values of the parameters
$key = $params->get ( 'key', '' );
$height = $params->get ( 'height', '' );
$width = $params->get ( 'width', '' );
$myLatitude = $params->get ( 'myLatitude', '' );
$myLongitude = $params->get ( 'myLongitude', '' );
$zoom = $params->get ( 'zoom', '' );
$type = $params->get ( 'type', '' );
$info_bool = $params->get ( 'info_bool', '' );
$info_text = $params->get ( 'info_text', '' );
$reset_link = $params->get ( 'reset_link', '' );
$controll = $params->get ( 'controll', '' );
$dragging = $params->get ( 'dragging', '' );
$mousewheel = $params->get ( 'mousewheel', '' );
$gbar = $params->get ( 'gbar', '' );
$kml = $params->get ( 'kml', '' );

if ($key != "") {
	echo "<script src=\"http://www.google.com/jsapi?key=$key\" type=\"text/javascript\"></script>";
	echo "<script type=\"text/javascript\">";
	
	echo "var myLatitude = $myLatitude;";
	echo "var myLongitude = $myLongitude;";
	echo "var Zoom = $zoom;";
	
	echo "google.load(\"maps\", \"2.x\");";
	echo "function initialize() {";
	echo "var map = new google.maps.Map2(document.getElementById(\"map\"));";
	if ($kml) {
		echo "var kml = new GGeoXml(\"$kml\");";
		echo "map.addOverlay(kml);";
		echo "map.setCenter(new GLatLng(myLatitude, myLongitude), Zoom);";
		echo "map.setMapType($type);";
	} else {
		
		if ($info_bool != "0") {
			echo "map.openInfoWindow(map.getCenter(),";
			echo "document.createTextNode(\"$info_text\"));";
		}
	}
	if ($controll == "smart") {
		echo "map.addControl(new GSmallMapControl());";
	}
	if ($controll == "type") {
		echo "map.addControl(new GMapTypeControl());";
	}
	if ($controll == "both") {
		echo "map.addControl(new GSmallMapControl());";
		echo "map.addControl(new GMapTypeControl());";
	}
	if ($controll == "large") {
		echo "map.addControl(new GLargeMapControl());";
	}
	if ($controll == "both2") {
		echo "map.addControl(new GLargeMapControl());";
		echo "map.addControl(new GMapTypeControl());";
	}
	if ($draging == "0") {
		echo "map.disableDragging();";
	}
	if ($draging == "1") {
		echo "map.enableDragging();";
	}
	if ($mousewheel == "0") {
		echo "map.disableScrollWheelZoom();";
	}
	if ($mousewheel == "1") {
		echo "map.enableScrollWheelZoom();";
	}
	if ($gbar == "0") {
		echo "map.disableGoogleBar();";
	}
	if ($gbar == "1") {
		echo "map.enableGoogleBar();";
	}
	echo "}";
	echo "google.setOnLoadCallback(initialize);";
	echo "</script>";
	
	echo "<div id=\"map\" style=\"width: $width";
	echo "px; height:$height";
	echo "px\"></div>";
	if ($reset_link == "1") {
		echo "<a href=\"javascript:initialize()\">Reset Map</a>";
	}
} else {
	echo "No Gmaps API Key given";
}
?>