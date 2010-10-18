# Transition Component #

## Version ##

This was versioned as 1.1 alpha.

## Introduction ##

Transition component is a CakePHP component to help your transitional pages logic.

- For instance, this bears most wizard parts.
- In almost every case, your method for action can be one-liner as like following codes:
		function action(){
			$this->Transition->automate('next_action', 'YourModel', 'previous_action');
		}

## Requirements ##

- CakePHP >= 1.2
- PHP >= 4

## Setup ##

With console:
	cd /path/to/app/controllers/components
	git clone git://github.com/hiromi2424/TransitionComponent.git transition

In controller's property section:
	var $components = array( ... , 'Transition');

## Summary ##

- checkData() is to check data(if given) with model validation and auto redirecting
- checkPrev() is to check previous page's session data exists.
- automate() is convenient method for checkData() and checkPrev().

## Sample ##

1. Simple Wizard Form

	class UsersController extends AppController{
		var $components = array('Transition');
		// base of user information
		function register() {
			// give a next action name
			$this->Transition->checkData('register_enquete');
		}
		// input enquete
		function register_enquete() {
			$this->Transition->automate(
				'register_confirm', // next action
				'Enquete', // model name to validate
				'register_confirm' // previous action to check
			);
		}
		// confirm inputs
		function register_confirm() {
			$this->Transition->automate(
				'register_save', // next
				null, // validate with current model
				'register_enquete', // prev
				'validateCaptcha' // virtual function to validate with captcha
			 );
			$this->set('data', $this->Transition->allData());
			$this->set('captcha', createCaptcha()); // virtual function to create a captcha
		}
		// stroring inputs
		function register_save() {
			// As like this, multi action name can be accepted
			$this->Transition->checkPrev(array(
				'register',
				'register_enquete',
				'register_confirm'
			));
			// mergedData() returns all session data saved on the actions merged
			if ($this->User->saveAll($this->Transition->mergedData()) {
				// Clear all of session data TransitionComponent uses
				$this->Transition->clearData();
				$this->Session->setFlash(__('Registration complete !!', true));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('Registration failed ...', true));
				$this->redirect(array('action' => 'register'));
			}
		}
	}


2. Transition among two Controllers

	class FirstController extends AppContoller {
		var $components = array('Transition');
		function one() {
			$this->Transition->checkData(array('controller' => 'second', 'action' => 'two'));
		}
		function three() {
			$this->Transition->checkPrev(array(
				'one',
				array('controller' => 'second', 'action' => 'two')
			));
		}
	}
	class SecondController extends AppContoller {
		var $components = array('Transition');
		function two() {
			$this->Transition->automate(
				array('controller' => 'first', 'action' => 'three'),
				null,
				array('controller' => 'first', 'action' => 'one')
			);
		}
	}
