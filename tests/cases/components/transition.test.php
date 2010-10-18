<?php

App::import('Controller', array('Component', 'Controller'), false);
App::import('Component', 'Transition');

class TransitionComponentTestController extends Controller {
	var $name = 'TransitionComponentTest';
	var $components = array('Transition');
	var $uses = array('TransitionModel');
	
	var $redirectTo = null;
	
	function redirect($url) {
		$this->redirectTo = Router::url($url);
		return true;
	}
}

class TransitionComponentAppModelController extends TransitionComponentTestController {
	var $name = 'TransitionComponentAppModel';
	var $uses = array('TransitionPost');
}

class TransitionModelBase extends CakeTestModel {
	var $useTable = false;
	var $validationSuccess = true;
	function validates() {
		return $this->validationSuccess;
	}
}

class TransitionModel   extends TransitionModelBase { var $name = 'TransitionModel'; }
class validationSuccess extends TransitionModelBase { var $name = 'ValidationSuccess'; }
class ValidationFail    extends TransitionModelBase {
	var $name = 'ValidationFail';
	var $validationSuccess = false;
}

class NormalValidation extends CakeTestModel {
	var $name = 'NormalValidation';
	var $useTable = false;
	var $validate = array('max25char' => array('rule' => array('maxLength', 25)));

	function triggerError($data) {
		foreach ($data[$this->name] as $key => $val) {
			$this->invalidate($key, $val);
		}
		return false;
	}
}

class ObjectValidation {
	function validates() {
		return true;
	}
}

if (!function_exists('validationFail')) {
	function validationFail() {
		return false;
	}
}

if (!function_exists('validationSuccess')) {
	function validationSuccess() {
		return true;
	}
}

class TransitionComponentTest extends CakeTestCase {
	var $Controller = null;
	var $fixtures = array('app.transition_post');
	var $sessionBaseKey = '';

	function start() {
		parent::start();
		ClassRegistry::config(array('table' => false));
		$this->__loadController();
	}

	function end() {
		$this->__shutdownController();
		parent::end();
	}

	function __loadController($params = array()) {
		if ($this->Controller !== null) {
			$this->__shutdownController();
			unset($this->Controller);
		}

		$controllerName = 'Test';
		if (!empty($params['controller'])) {
			$controllerName = $params['controller'];
			unset($params['controller']);
		}

		$controllerName = 'TransitionComponent' . $controllerName . 'Controller';
		$Controller = new $controllerName();
		$Controller->params = array(
			'controller' => $Controller->name,
			'action' => 'test_action',
		);
		$Controller->params = array_merge($Controller->params, $params);
		$Controller->constructClasses();
		$Controller->Component->initialize($Controller);
		$Controller->beforeFilter();
		$Controller->Component->startup($Controller);
		$this->Controller = $Controller;
		
		$this->sessionBaseKey = "Transition." . Inflector::underscore($Controller->name);
	}

	function __shutdownController() {
		$this->Controller->Transition->Session->delete($this->sessionBaseKey);
		$this->Controller->Transition->Session->delete('Message');
		$this->Controller->Component->shutdown($this->Controller);
	}

	function testStartup() {
		$c = $this->Controller;
		$t = $c->Transition;
		$s = $t->Session;
		
		$t->automation = true;
		$this->assertTrue($t->startup($c));
		$t->automation = array();
		$this->assertTrue($t->startup($c));
		$t->automation = array('test_action' => array());
		$this->assertTrue($t->startup($c));
		$t->automation = array('test_action' => array('prev' => 'prev_action'));
		$this->assertFalse($t->startup($c));
		$this->assertEqual($s->read('Message.flash.message'), $t->messages['prev']);
	}

	function testAutomate() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;
		$s = $t->Session;
		$c->data = array('dummy');

		$this->assertTrue($t->automate(null));
		$this->assertFalse($t->automate('next_action', 'ValidationFail'));
		$this->assertFalse($t->automate(null, null, 'prev_action'));
		$this->assertFalse($t->automate('next_action', 'ValidationFail', 'prev_action'));

		$t->setData('prev_action', 'dummy');
		$this->assertTrue($t->automate(null, null, 'prev_action'));
		$this->assertFalse($t->automate('next_action', 'ValidationFail', 'prev_action'));
		$this->assertTrue($t->automate('next_action', 'ValidationSuccess', 'prev_action'));

		$c->data = array('NormalValidation' => array('max25char' => 'this will be handled as invalid'));
		$NormalValidation = ClassRegistry::init('NormalValidation');
		$result = $t->automate('next_action', 'ValidationSuccess', 'prev_action', array($NormalValidation, 'triggerError'), array('invalid' => 'validation failed'));
		$this->assertFalse($result);
		$this->assertEqual($s->read('Message.flash.message'), 'validation failed');
		$this->assertFalse(empty($NormalValidation->validationErrors));
		$this->assertEqual($c->redirectTo, '/next_action');

		$t->clearData();
		$result = $t->automate('next_action', 'ValidationSuccess', 'prev_action', null, array('prev' => 'no previous'));
		$this->assertFalse($result);
		$this->assertEqual($s->read('Message.flash.message'), 'no previous');
	}

	function testCheckPrev() {
		$c = $this->Controller;
		$t = $c->Transition;
		$s = $t->Session;

		$result = $t->checkPrev('unknown');
		$this->assertFalse($result);
		$this->assertEqual($c->redirectTo, '/unknown');

		$t->setData('prev_action', 'dummy');
		$result = $t->checkPrev('prev_action');
		$this->assertTrue($result);

		$result = $t->checkPrev(array('prev_action', 'unknown'));
		$this->assertFalse($result);
		$this->assertEqual($c->redirectTo, '/unknown');

		$t->setData('old_action', 'horoharahirehare-');
		$result = $t->checkPrev(array('prev_action', 'old_action'));
		$this->assertTrue($result);

		$t->checkPrev('unknown', 'no prev');
		$this->assertEqual($s->read('Message.flash.message'), 'no prev');

		$t->checkPrev('unknown', null, 'index');
		$this->assertEqual($c->redirectTo, '/index');
	}

	function testCheckData() {
		$c = $this->Controller;
		$t = $c->Transition;
		$s = $t->Session;
		$c->data = array();

		$this->assertTrue($t->checkData());

		$c->data = array('TransitionModel' => array('dummy' => 2));
		$this->assertTrue($t->checkData(null, false));
		$result = $t->checkData(array('controller' => 'tests', 'action' => 'next_action'));
		$this->assertTrue($result);
		$this->assertEqual($c->redirectTo, '/tests/next_action');
		$this->assertIdentical($t->data('test_action'), $c->data);

		$t->clearData();
		$t->autoRedirect = false;
		$c->redirectTo = null;

		$result = $t->checkData(array('controller' => 'tests', 'action' => 'next_action'));
		$this->assertNull($c->redirectTo);

		$t->clearData();
		$s->delete('Message');
		$c->data = array('NormalValidation' => array('max25char' => 'This column will be failed because of too long string'));

		$t->checkData(null, 'NormalValidation', null, 'validation was fail');
		$this->assertEqual($s->read('Message.flash.message'), 'validation was fail');
		$this->assertIdentical($t->data('test_action'), $c->data);

		$c->data = null;
		$t->setData('test_action', 'test_data');
		$t->checkData();
		$this->assertEqual($c->data, 'test_data');

	}

	function testValidateModel() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$c->data = array($c->modelClass => array('dummy' => 2));

		$Success = ClassRegistry::init('ValidationSuccess');
		$Fail = ClassRegistry::init('ValidationFail');
		$NormalValidation = ClassRegistry::init('NormalValidation');

		$this->assertTrue($t->validateModel('NotExistModel'));

		$this->assertTrue($t->validateModel('ValidationSuccess'));
		$this->assertFalse($t->validateModel('ValidationFail'));
		$this->assertTrue($t->validateModel(null, 'validationSuccess'));
		$this->assertFalse($t->validateModel(null, 'validationFail'));
		$this->assertTrue($t->validateModel(null));

		$c->TransitionModel->bindModel(array('belongsTo' => array('AssociatedModel')));
		$this->assertTrue($t->validateModel('AssociatedModel'));

		$this->assertTrue($t->validateModel($Success));
		$this->assertFalse($t->validateModel($Fail));
		$this->assertTrue($t->validateModel(null, array($Success, 'validates')));
		$this->assertFalse($t->validateModel(null, array($Fail, 'validates')));
	
		$this->assertFalse($t->validateModel('ValidationSuccess', array($Fail, 'validates')));
		$this->assertFalse($t->validateModel('ValidationFail', array($Success, 'validates')));
		$this->assertFalse($t->validateModel($Success, array($Fail, 'validates')));
		$this->assertTrue($t->validateModel($Success, array($Success, 'validates')));

		$this->assertTrue($t->validateModel($Success, array(new ObjectValidation, 'validates')));

		$c->data = array('NormalValidation' => array('max25char' => 'This column will be failed because of too long string'));
		$t->validateModel('NormalValidation');
		$this->assertFalse(empty($NormalValidation->validationErrors));

		$NormalValidation->create(false);
		$t->validateModel($NormalValidation);
		$this->assertFalse(empty($NormalValidation->validationErrors));

		$NormalValidation->create(false);
		$c->data = array('NormalValidation' => array('maxchar25' => 'this column will be pass'));
		$t->validateModel($NormalValidation);
		$this->assertTrue(empty($NormalValidation->validationErrors));

		$this->__loadController(array('controller' => 'AppModel'));
		$c = $this->Controller;
		$t = $c->Transition;
		$c->data = array('NormalValidation' => array('max25char' => 'This column will be failed because of too long string'));

		$this->assertTrue($t->validateModel('TransitionPost'));

		$TransitionPost = ClassRegistry::init('TransitionPost');
		$result = $t->validateModel('NormalValidation', array($TransitionPost, 'validates'));

		$this->assertFalse($result);
		$this->assertFalse(empty($NormalValidation->validationErrors));
	}

	function testAutoLoadModels() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$this->assertEqual($t->_autoLoadModels(null), array('TransitionModel'));
		$this->assertEqual($t->_autoLoadModels(false), null);
		$this->assertEqual($t->_autoLoadModels('Model'), array('Model'));
		$this->assertEqual($t->_autoLoadModels(array('Model')), array('Model'));
		$this->assertEqual($t->_autoLoadModels(array('Model1', 'Model2', 'Model3')), array('Model1', 'Model2', 'Model3'));
		
		$object = new Object();
		$this->assertIdentical($t->_autoLoadModels($object), array($object));
	}

	function testSessionKey() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;

		$this->assertEqual($t->sessionKey(null), $this->sessionBaseKey);
		$this->assertEqual($t->sessionKey('my_key'), 'Transition.transition_component_test.my_key');
		$this->assertEqual($t->sessionKey('my_key', 'my_controller_key'), 'Transition.my_controller_key.my_key');
		$this->assertEqual($t->sessionKey(array('controller' => 'my_controller')), 'Transition.my_controller');
		$this->assertEqual($t->sessionKey(array('controller' => 'MyController')), 'Transition.MyController');
		$this->assertEqual($t->sessionKey(array('controller' => '')), 'Transition.');
		$this->assertEqual($t->sessionKey(array('controller' => ' ')), 'Transition. ');
		$this->assertEqual($t->sessionKey(array('controller' => 'my_controller', 'action' => 'my_action')), 'Transition.my_controller.my_action');

		$t->sessionBaseKey = 'my_sessionkey';
		$this->assertEqual($t->sessionKey(null), 'my_sessionkey.transition_component_test');

		$c->name = 'my_controller';
		$result = $t->sessionKey(null);
		$this->assertEqual($result, 'my_sessionkey.my_controller');

		$t->sessionBaseKey = 'Transition';
		$c->name = 'TransitionComponentTest';
	}
	
	function testDataMethods() {
		$this->__loadController();
		$c = $this->Controller;
		$t = $c->Transition;
		$s = $t->Session;

		$t->setData('param1', array('testdata' => 'hoge'));
		$expected = array('testdata' => 'hoge');
		
		$this->assertEqual($s->read($this->sessionBaseKey . '.param1'), $expected);
		$this->assertEqual($t->data('param1'), $expected);
		$this->assertEqual($t->data('param2'), null);
		$this->assertEqual($t->allData(), array('transition_component_test' => array('param1' => $expected)));
		
		$this->assertTrue($t->setData('param2', array('User' => array('id' => 1, 'name' => 'user1', 'age' => 46))));
		$this->assertTrue($t->setData('param3', array('User' => array('id' => 2, 'name' => 'user2'))));

		$expected = array('testdata' => 'hoge', 'User' => array('id' => 2, 'name' => 'user2', 'age' => 46));
		$this->assertEqual($t->mergedData(), $expected);
		$this->assertEqual($t->mergedData(array('Set', 'merge')), $expected);
		$expected = array('testdata' => 'hoge', 'User' => array('id' => 1, 'name' => 'user1', 'age' => 46));
		$this->assertEqual($t->mergedData('Set::pushDiff'), $expected);
		$this->assertEqual($t->mergedData(array('Set', 'pushDiff')), $expected);
		$expected = array('testdata' => 'hoge', 'User' => array('id' => array(1, 2), 'name' => array('user1', 'user2'), 'age' => 46));
		$this->assertEqual($t->mergedData('array_merge_recursive'), $expected);

		$this->assertTrue($t->deleteData('param2'));
		$this->assertFalse($s->check($this->sessionBaseKey . '.param2'));
		$this->assertFalse($t->data('param2'));

		$this->assertTrue($t->clearData());
		$this->assertFalse($s->check($this->sessionBaseKey . '.param1'));
		$this->assertFalse($s->check($this->sessionBaseKey . '.param3'));
		$this->assertNull($t->mergedData());
		$this->assertTrue($t->clearData());
		$this->assertFalse($t->delData(null));
		$this->assertFalse($t->deleteData('param2'));
	}
}