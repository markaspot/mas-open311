<?php

$config['open311']['jurisdiction_id'] = "mas-city.com";

// changeset - String - Sortable field that specifies the last time this document was updated.
$config['open311']['changeset'] = "2011-06-21 00:00";
// contact - String - Human readable information on how to get more information on this provider.
$config['open311']['contact'] = "You can email or call for assistance api@mas-city.com +1 (555) 555-5555";
// key_service - String - Human readable information on how to get an API key.
$config['open311']['key_service'] = "You can request a key here: http://api.mas-city.com/api_key/request<";
//endpoints.endpoint.url - String - URL of the endpoint provider
$config['open311']['url'] = "http://mas-city.com/open311"; 

$config['open311']['type'] = "test"; // production, test, dev
$config['open311']['formats'] = array('format' => array('text/xml', 'application/json'));

?>