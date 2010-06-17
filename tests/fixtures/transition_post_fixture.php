<?php 
class TransitionPostFixture extends CakeTestFixture {
	var $name = 'TransitionPost';
	
	var $fields = array(
		'id' => array('type' => 'integer', 'key' => 'primary'),
		'title' => array('type' => 'string', 'length' => 255, 'null' => false),
		'body' => 'text',
		'published' => array('type' => 'integer', 'default' => '0', 'null' => false),
		'created' => 'datetime',
		'updated' => 'datetime'
	 );
}