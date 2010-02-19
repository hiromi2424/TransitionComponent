<?php
/**
 * TransitionComponent. Among form pages , auto validation and auto redirect.
 *
 * PHP versions 4 and 5 , CakePHP => 1.2
 *
 * @copyright     Copyright 2010, hiromi
 * @package       cake
 * @subpackage    cake.app.controllers.components.transition
 * @license       Free
 */


/**
 * TransitionComponent.
 * Among form pages , auto validation and auto redirect.
 * This will use Session.
 *
 * @package       cake
 * @subpackage    cake.app.controllers.components.transition
 */

class TransitionComponent extends Object{

/**
 * Components to use.
 *
 * @var array name of components
 * @access public
 */
	var $components = array('Session');

/**
 * Turns on or off automation on startup.
 * When Array given, This automate within these key as actions.
 *
 * Example.
 * // beforeFilter  in controller
 * $this->Transition->automation = array(
 *   'action' => array(
 *     'nextStep'          => 'nextAction',
 *     'models'            => array('Model1','Model2'),
 *     'prev'              => 'prevAction',
 *     'validationMethod'  => array(&$this->Model3,'behaviorMethod'),
 *     'messages'          => array(
 *       'invalid' => __('your input was wrong.',true),
 *       'prev'    => __('wrong transition.',true),
 *     ),
 *   )
 * );
 * 
 * @var mixed array or false
 * @access public
 */
	var $automation = false;

/**
 * Messages set with Session::setFlash().
 * "invalid" key , When it cannot pass validation.
 * "prev"    key , When session has no data for previous action.
 *
 * @var array default messages with key
 * @access public
 */
	var $messages = array();

/**
 * Turns on or off auto loading session data to Controller::data.
 *
 * @var boolean auto loading data
 * @access public
 */
	var $autoComplete   = true;

/**
 * Turns on or off auto redirect when data passes validation or session data of previous action is empty.
 *
 * @var boolean auto redirection
 * @access public
 */
	var $autoRedirect   = true;

/**
 * Default models.
 *
 * @var array models
 * @access public
 */
	var $models         = null;

/**
 * Default validation method.
 *
 * @var callback validation method
 * @access public
 */
	var $validationMethod = null;

/**
 * Holds the reference of current controller
 *
 * @var object controller
 * @access private
 */
	var $_controller;
/**
 * Holds the current action of the controller
 *
 * @var string action name
 * @access private
 */
	var $action;

/**
 * Initialize the TransitionComponent
 *
 * @param object $controller Controller instance for the request
 * @param array $settings Settings to set to the component
 * @return void
 * @access public
 */
	function initialize(&$controller,$settings = array()){
		// set default
		$this->messages = array(
			'invalid' => __('Input Data was not able to pass varidation. Please, try again.', true),
			'prev'    => __('Session timed out.', true)
		);
		// configure.
		$this->_set($settings);
		$this->_controller =& $controller;
		$this->action = $controller->params['action'];
	}

/**
 * Component startup. with automation options , It will automate.
 *
 * @param object $controller Instantiating controller
 * @return void
 * @access public
 */
	function startup(&$controller){
		if($this->automation !== false){
			$doAutomate = 
				is_array($this->automation) &&
				array_key_exists($this->action,$this->automation)
			;
				
			if($doAutomate){
				$automation = $this->automation[$this->action];
				$defaults = array(
					'nextStep'         => null,
					'models'           => $this->models,
					'prev'             => null,
					'validationMethod' => $this->validationMethod,
					'messages'         => $this->messages,
				);
				$automation = array_merge($defualts,$automation);
				extract($automation);
				return $this->automate($nextStep,$models,$prev,$validationMethod,$messages);
			}
		}
		return true;
	}

/**
 * Automation method.
 *
 * @param mixed $nextStep Next step url (will be given Controller::redirect())
 * @param mixed $models Models for validation
 * @param mixed $prev Previous action for check
 * @param callback $validationMethod Method to validate
 * @param array $messages Messages to Controller::setFlash()
 * @return boolean Success
 * @access public
 */
	function automate($nextStep,$models = null,$prev = null,$validationMethod = null,$messages = array()){
		$c =& $this->_controller;
		$messages = array_merge($this->messages,$messages);
		
		if($prev !== null){
			if(!$this->checkPrev($prev,$messages['prev'])){
				return false;
			}
		}
		if($nextStep !== null){
			if(!$this->checkData($nextStep,$models,$validationMethod,$messages['invalid'])){
				return false;
			}
		}
		return true;
	}

/**
 * Check previous session data.
 *
 * @param mixed $prev Previous action for check
 * @param string $message A Message to Controller::setFlash()
 * @param string $prevAction Previous action to Redirect.
 * @return boolean Success
 * @access public
 */
	function checkPrev($prev,$message = null,$prevAction = null){
		if(is_array($prev)){
			foreach($prev as $p){
				if(!$this->checkPrev($p,$message,$prevAction)){
					return false;
				}
			}
			return true;
		}
		if($prevAction === null){
			$prevAction = array('action'=>$prev);
		}
		if($message === null){
			$message = $this->messages['prev'];
		}
		if(!$this->Session->check($this->sessionKey($prev))){
			if($message !== false){
				$this->Session->setFlash($message);
			}
			if($this->autoRedirect){
				$this->_controller->redirect($prevAction);
			}
			return false;
		}
		return true;
	}

/**
 * Check data of current controller with auto validation , auto redirection , auto setFlash() ,  and auto restoring data
 *
 * @param mixed $nextStep Next step url (will be given Controller::redirect())
 * @param mixed $models Models for validation
 * @param callback $validationMethod Method to validate
 * @param array $messages Messages to Controller::setFlash()
 * @param string $sessionKey Session key to store
 * @return boolean Success
 * @access public
 */
	function checkData($nextStep = null,$models = null,$validationMethod = null,$message = null,$sessionKey = null){
		$models = $this->_autoLoadModels($models);
		$c =& $this->_controller;
		if($sessionKey === null){
			$sessionKey = $this->action;
		}
		
		if($message === null){
			$message = $this->messages['invalid'];
		}
		if(!empty($c->data)){
			$this->setData($sessionKey,$c->data);
			
			if($models === null){
				return false;
			}
			
			$result = true;
			foreach($models as $model){
				if( !$this->validateModel($model) ){
					$result = false;
				}
			}
			if($result){
				if($nextStep !== null && $this->autoRedirect){
					$nextStep = !is_array($nextStep)?array('action'=>$nextStep):$nextStep;
					$c->redirect($nextStep);
				}
			}else{
				if($message !== false){
					$this->Session->setFlash($message);
				}
				return false;
			}
		}elseif($this->autoComplete && $this->Session->check($this->sessionKey($sessionKey))){
			$c->data = $this->data($sessionKey);
		}
		
		return true;
	}

/**
 * Validation with model name.
 *
 * @param mixed $models Models for validation
 * @param callback $validationMethod Method to validate
 * @return boolean Success
 * @access public
 */
	function validateModel($model,$validationMethod = null){
		if($model === null){
			return true;
		}
		if($validationMethod === null){
			$validationMethod = $this->validationMethod;
		}
		
		$c =& $this->_controller;
		
		/*
		 * Loading Model object.
		 */
		if(!is_object($model)){
			$controllerModel = $c->modelClass;
			$modelName = Inflector::classify($model);
			
			$controllerHasModel = 
				property_exists($c,$modelName) ||
				property_exists($c->{$controllerModel},$modelName)
			;
			if( $controllerHasModel ){
				$model = property_exists($c->{$controllerModel},$modelName)?$c->{$controllerModel}->{$modelName}:$c->{$modelName};
				if(get_class($model) == 'AppModel'){
					if(!class_exists($modelName)){
						App::import('Model',$modelName);
					}
					if(!class_exists($modelName)){
						return false;
					}
					$model = new $modelName();
				}
			}else{
				if(!class_exists($modelName)){
					App::import('Model',$modelName);
				}
				if(!class_exists($modeName)){
					return false;
				}else{
					$model = new $modelName();
				}
			}
		}
		
		$data = $c->data;
		
		// User method.
		if($validationMethod !== null){
			$isModelMethod = 
				is_array($validationMethod) &&
				is_object(current($validationMethod)) &&
				is_a(current($validationMethod),'Model')
			;
			
			if($isModelMethod){
				return call_user_func($validationMethod,$data);
			}else{
				return call_user_func($validationMethod,&$model,$data);
			}
		}
		
		
		// $model->create();
		// $this->_controller->debug($c->data);
		$result = true;
		
		if(!empty($data)){
			$model->set($data);
			if(!$model->validates()){
				$result = false;
			}
		}
			//var_dump($model->beforeValidate());
			// exit;
		if(!$result){
			// debugging in development
			// $c->debug($model->validationErrors);
		}
		
		return $result;
	}
	
	function _autoLoadModels($models){
		if($models === null){
			if(!empty($this->models)){
				return $this->models;
			}
			$c =& $this->_controller;
			if($c->modelClass !== null && $c->{$c->modelClass}){
				$models = $c->modelClass;
			}
		}
		
		if($models !== null && !is_array($models)){
			$models = array($models);
		}
		return $this->models = $models;
	}

/**
 * Get session data from key.
 *
 * @param string $key Key name
 * @return mixed Session data or null
 * @access public
 */
	function data($key){
		$key = $this->sessionKey($key);
		if($this->Session->check($key)){
			return $this->Session->read($key);
		}
		return null;
	}

/**
 * Get all of session data from key.
 *
 * @return mixed Session data or null
 * @access public
 */
	function allData(){
		return $this->data(null);
	}

/**
 * Get merged session data from key.
 *
 * @return mixed Merged session data or null
 * @access public
 */
	function mergedData(){
		$allData = $this->allData();
		if(empty($allData)){
			return $allData;
		}
		
		$merged = array();
		foreach($allData as $action => $data){
			$merged = array_merge_recursive($merged , $data);
		}
		
		return $merged;
	}

/**
 * Set session data with key.
 *
 * @param string $key Key name
 * @param mixed $data data to set
 * @return boolean Success
 * @access public
 */
	function setData($key,$data){
		return $this->Session->write($this->sessionKey($key),$data);
	}

/**
 * Get Session key.
 *
 * @param string $key Key name
 * @param string $cname controller name(deprecated argument)
 * @return string Session key
 * @access public
 */
	function sessionKey($key,$cname = null){
		$key   = $key   === null ? "":".$key";
		$cname = $cname === null ? ".".$this->_controller->name:".$cname";
		return 'Transition'.$cname.$key;
	}

/**
 * Delete Session data from key.
 *
 * @param string $key Key name
 * @return boolean Success
 * @access public
 */
	function delData($key){
		$key = $this->sessionKey($key);
		if($this->Session->check($key)){
			return $this->Session->delete($key);
		}
	}

/**
 * Clear Session data.
 *
 * @param string $key Key name
 * @return boolean Success
 * @access public
 */
	function clearData(){
		if($this->Session->check('Transition')){
			return $this->Session->delete('Transition');
		}
		return true;
	}
	
}

