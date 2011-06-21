<?php
/**
 * Mark-a-Spot Open311 Controller
 *
 * Everything about controlling requests and services
 *
 * Copyright (c) 2011 Holger Kreis
 * http://www.mark-a-spot.org
 *
 *
 * PHP version 5
 * CakePHP version 1.3
 *
 * @copyright  2010, 2011 Holger Kreis <holger@markaspot.org>
 * @link   	http://mark-a-spot.org/
 * @version	1.5.1 
 */

class Open311Controller extends AppController {

	var $name = 'Open311';
	var $uses = array('Marker','Category');
	
	
	var $components = array(
	
		'RequestHandler', 'Geocoder', 'Cookie', 'Notification', 'Transaction','Open311',
			
		/*
		 * Rest Plugin by KVZ load it as needed
		 *
		 *
		 */
		'Rest.Rest' => array(
			'catchredir' => false, // Recommended unless you implement something yourself
			'viewsFromPlugin' => false,
			'ratelimit' => null, array(
				'classlimits' => array(
					'Marker' => array('-1 hour', 100)
				),
			'	identfield' => 'apikey',
			'	ip_limit' => array('-1 hour', 60),  // For those not logged in
			),
			'log' => array(
				'model' => 'Rest.RestLog',
				'dump' => false, // Saves entire in + out dumps in log. Also see config/schema/rest_logs.sql
			),
			'debug' => 0,
		),
		
	);
	
	public function beforeFilter () {
		// Try to login user via REST
		if ($this->Rest->isActive()) {

			if ($this->Auth->user('id') && $this->params['url']['apikey']) {
				$success = true;
				$credentials['apikey'] = $this->params['url']['apikey'];
			} else {

				$credentials = $this->Rest->credentials();
			
				$credentials["email_address"] = $credentials["username"];
				$credentials["password"] = $this->Auth->password($credentials["password"]);
				$this->Auth->fields = array('username' => 'email_address', 'password' => 'password');
				
				$success = $this->Auth->login($credentials);
			}
			
			if (!$success) {
				$msg = sprintf('Unable to log you in with the supplied credentials.');
				return $this->Rest->abort(array('status' => '401', 'error' => $msg));
			} 
			
			// Additionally Check API key 
			$apikey = "MasAPIkey";
			
			if ($apikey !== $credentials['apikey']) {
				$this->Auth->logout();
				$msg = sprintf('Invalid API key: "%s"', $credentials['apikey']);
				return $this->Rest->abort(array('status' => '401', 'error' => $msg));
			}

		}
		parent::beforeFilter();
	}

	
	/**
	* Shortcut so you can check in your Controllers wether
	* REST Component is currently active.
	*
	* Use it in your ->redirect() and ->flash() methods
	* to forward errors to REST with e.g. $this->Rest->error()
	*
	* @return boolean
	*/
	
	protected function _isRest() {
		return !empty($this->Rest) && is_object($this->Rest) && $this->Rest->isActive();
	}


	/**
	* Handle Geocoding and Files Uploaded
	*
	* @return array
	*/

	
	public function redirect($url, $status = null, $exit = true) {
		
		if ($this->_isRest()) {
			// Just don't redirect.. Let REST die gracefully
			// Do set the HTTP code though
			parent::redirect(null, $status, false);
			$this->Rest->abort(compact('url', 'status', 'exit'));
		}
		parent::redirect($url, $status, $exit);
	}

	/**
	* get all requests / get all markers
	*
	* @return void
	*/

	function requests() {
		// define markers by role later, this one for readonly user level
		$markers = $this->Marker->find('all',array( 
			'contain' => array('Category','Status','Attachment', 'User'),
			'fields' => array('Marker.id', 'Marker.subject', 'Marker.street','Marker.zip','Marker.city',
					'Marker.status_id', 'Marker.description', 'Marker.lat', 'Marker.lon',
						'Marker.rating', 'Marker.votes', 'Marker.created', 'Marker.modified', 'Category.name',
							'Status.id', 'Status.name', 'Category.hex', 'Status.hex', 'User.nickname'),
			'order' => 'Marker.modified DESC')
			);

		$service_requests = $this->Open311->mapMarkers($markers);
		$this->set(compact('service_requests'));
		$this->render('requests');
	}

	/**
	* get single requests / get all markers
	*
	* @return void
	*/

	function request($id) {
		$marker[0] = $this->Marker->findById($id);
		$service_requests = $this->Open311->mapMarkers($marker);
		$this->set(compact('service_requests'));
		$this->render('requests');
	}
	
	
	/**
	* get all services / get all categories
	*
	* @return void
	*/
	
	function services() {
		$categories = $this->Category->find('all');
		$services = $this->Open311->mapCategories($categories);
		$this->set(compact('services'));
		$this->render('services');
	}

	/**
	* post request / add marker
	*
	* @return void
	*/

	function add() {
		if (!isset($this->params['form']['jurisdiction_id']) || !isset($this->params['form']['service_code'])){
			return $this->Rest->abort(array(
				'status' => '400', 'error' => __('service_code or jurisdiction_id was not provided', true)));
		} else {
			$this->data = $this->Open311->mapRequest($this->params['form']);
		}

		if ($this->Marker->saveAll($this->data, array('validate' => false))) {
			
			$id = $this->Marker->id;
			
			// Now read E-Mail Adress which is assigned to category (just saved in form)
			$categoryUserId = $this->Marker->Category->read(array('user_id'),$this->data['Marker']['category_id']);
			$catUserId = $categoryUserId['Category']['user_id'];
			
			if ($catUserId != ""){
				$recipient = $this->User->field('email_address',array('id =' => $catUserId));
			} else {
				$stringOfAdmins = implode(',', $this->_getAdminMail());
				$recipient = $stringOfAdmins;
			}

			//
			// call Notification Component and send mail to all Admins
			//
			$nickname = "Open311 App User";

			$cc[] = "";
			
			$this->Notification->sendMessage("markerinfoadmin",$id, $nickname, $recipient,$cc);
				
			// create object for ReST Response
			$response = array(
				'success' => true,
				'message' => 	sprintf(__('The Marker ID# %s has been saved.',true),
				substr($id, 0, 8)),
			);
			$this->Rest->info($response);
			$this->Transaction->log($id);
			
			$marker[0] = $this->Marker->findById($id);
			$service_requests = $this->Open311->mapMarkers($marker);
			$this->set(compact('service_requests'));
			$this->render('requests');
			
		} else {
		
			// get errors from  model
			$errors = $this->Marker->invalidFields();
			
			// create object for ReST Response
			$response = array(
				'success' => false,
				'message' => $errors,
			);
			
			// $this->Rest->error($response);
			// As long as jquery does not support responseText on error header (400)
			// we have to distinguish between ajax requests and normal curl requests	
			if ($this->RequestHandler->isAjax()) {
				return $this->Rest->error($response);
			} else {
				return $this->Rest->abort(array('status' => '400', 'error' => $errors));

			}
			
		}

	} 


	function delete($id) {
		
		if($this->Marker->delete($id, $cascade = true)) {

			$message = array(
				'message' => __('Marker deleted.', true));
			$this->Rest->info($message);
			
		} else {
		
			$errors = "MarkerId not valid";

			return $this->Rest->abort(array('status' => '400', 'error' => $errors));
		}
	}
	
} 
?>
