<?php


/**
 * Routing for Open311 Plugin
 */
	Router::parseExtensions('json','xml');

	Router::connect("/open311/requests",array('plugin' => 'open311' , 'controller' => 'open311','action' => 'requests', '[method]' => 'GET'));

	Router::connect("/open311/services",array('plugin' => 'open311' , 'controller' => 'open311','action' => 'services', '[method]' => 'GET'));

	Router::connect("/open311/requests/:id",array('plugin' => 'open311' , 'controller' => 'open311','action' => 'request', '[method]' => 'GET'),array('id' => '[0-9]+|[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}', 'pass' => array('id')));

	Router::connect("/open311/requests",array('plugin' => 'open311' ,'controller' => 'open311','action' => 'add', '[method]' => 'POST'),array('id' => '[0-9]+|[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}', 'pass' => array('id')));
	
	Router::connect("/open311/discovery",array('plugin' => 'open311' ,'controller' => 'open311','action' => 'discovery', '[method]' => 'GET'));

?>