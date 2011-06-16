<?php
/**
 * Mark-a-Spot Twitter Component
 *
 * Everything about the 1.5 *
 * Copyright (c) 2010 Holger Kreis
 * http://www.mark-a-spot.org
 *
 *
 * PHP version 5
 * CakePHP version 1.3
 *
 * @copyright  2010, 2011 Holger Kreis <holger@markaspot.org>
 * @link       http://mark-a-spot.org/
 * @version    1.6.1
 */

class Open311Component extends Object {

	var $name = 'Open311';
	var $data = null;
	var $latlng = null;
	var $open311 = null;
	var $components = array('Auth','Geocoder');
	var $helpers = array(
		'Media.Media' => array(
			'versions' => array(
			's', 'xl'
			)
		)
	);

	function startup(&$controller) {
		$this->Controller =& $controller;
		$this->User = ClassRegistry::init('User');
		$this->Marker = ClassRegistry::init('Marker');
		$this->Category = ClassRegistry::init('Category');
		$this->Media = ClassRegistry::init('Media.Attachment');
	}

	

	/**
	 * map Marker to Open311 Api
	 * 
	 * 
	 */

	function mapMarkers($markers) {
		foreach ($markers as $marker) {
			$open311_element['request']['service_request_id'] = $marker['Marker']['id'];
			$open311_element['request']['status'] = $marker['Status']['name'];
			//$open311_element['status_notes'] = $marker['Comment'][0]['description'];

			$open311_element['request']['service_name'] = $marker['Category']['name'];
			$open311_element['request']['service_code'] = $marker['Category']['id'];
			$open311_element['request']['requested_datetime'] =  date('c',strtotime($marker['Marker']['created']));
			$open311_element['request']['updated_datetime'] =  date('c',strtotime($marker['Marker']['modified']));
//			add key to config later
//			$open311_element['expected_datetime'] =  date('c',strtotime($marker['Marker']['modified']));
			$open311_element['request']['address'] =  $marker['Marker']['street']." ".$marker['Marker']['zip']." ".$marker['Marker']['city'];
			$open311_element['request']['zipcode'] =  $marker['Marker']['zip'];
			$open311_element['request']['lat'] =  $marker['Marker']['lat'];
			$open311_element['request']['long'] =  $marker['Marker']['lon'];
			//$open311_element['media_url'] =  $marker['Attachment']['basename'];

			$open311[] = $open311_element;
		}
		return $open311;
	}


	function mapCategories($categories) {	
		$open311 = null;
		foreach ($categories as $category) {

			$open311_element['service']['service_code'] = $category['Category']['id'];
			$open311_element['service']['service_name'] = $category['Category']['name'];
			$open311_element['service']['description'] = $category['Category']['decription'];
			$open311_element['service']['metadata'] = $category['Category']['metadata'];
			$open311_element['service']['type'] = $category['Category']['type'];
			$open311_element['service']['keywords'] = $category['Category']['keywords'];
			$open311_element['service']['group'] = $category['Category']['group'];

			$open311[] = $open311_element;
		}

		return $open311;
	}
	
	
	function mapRequest($data) {
		if ($data['lat'] == "") {
			$latlng = $this->Geocoder->getLatLng($data['address_string']);
			$this->data['Marker']['lat'] = $latlng['lat'];
			$this->data['Marker']['lon'] = $latlng['lng'];
		} else {

			$address = $this->Geocoder->getAddress($data['lat'],$data['long']);

			$this->data['Marker']['zip'] = $address['zip'];
			$this->data['Marker']['city'] = $address['city'];
			$this->data['Marker']['street'] = $address['street'];

			$this->data['Marker']['lat'] = $data['lat'];
			$this->data['Marker']['lon'] = $data['long'];
		}
		
		$this->data['Marker']['description'] = "";
		$this->data['Attachment'][0]['model'] = "Marker";
		$this->data['Attachment'][0]['group'] = "attachment";
		$this->data['Attachment'][0]['file'] =	$data['media_url'];
		
		// Read ServiceName as Subject
		$this->Categoy->id = $data['service_code'];
		$serviceName = $this->Category->find('all', array('conditions' => array('Category.id' => $data['service_code'])));
		$this->data['Marker']['subject'] = $serviceName[0]['Category']['name'];
		

		// Set user_id with logged in Session posted by registered user
		$this->data['Marker']['user_id'] = $this->Auth->user('id');

		$this->data['Marker']['status_id'] = 2;	
		$this->data['Marker']['category_id'] = $data['service_code'];

		return $this->data;
	}

}