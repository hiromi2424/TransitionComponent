# Transition Component #

## Version ##

This was versioned as 1.0 stable.

## Introduction ##

Transition component is a CakePHP component to help your transitional pages logic.
This works almost part of wizard.
Almost every case, your mehod for action can be one-liner as following code:
	function action(){
		$this->Transition->automate('nextAction','Model','prevAction');
	}

## Requirements ##

- CakePHP >= 1.2
- PHP >= 4 (probably)

## Setup ##

With console:
	cd /path/to/app/controllers/components
	git clone git://github.com/hiromi2424/TransitionComponent.git

In controller's property section:
	var $components = array( ... , 'Transition');

## Summary ##

- checkData() is to check data(if given) with model validation and auto redirecting
- checkPrev() is to check previous page's session data exists.
- automate() is convenient method for checkData() and checkPrev().

## Sample ##

	class UsersController extends AppController{
		var $components = array('Transition');
		// base of user information
		function register(){
			// give a next action name
			$this->Transition->checkData('register_enquete');
		}
		// input enquete
		function register_enquete(){
			$this->Transition->automate(
				'register_confirm', // next action
				'Enquete', // model name to validate
				'register_confirm' // previous action to check
			);
		}
		// confirm inputs
		function register_confirm(){
			$this->Transition->automate(
				'register_save', // next
				null, // validate with current model
				'register_enquete', // prev
				'validateCaptcha' // virtual function to validate with captcha
			 );
			$this->set('data',$this->Transition->allData());
			$this->set('captcha',createCaptcha()); // virtual function to create a captcha
		}
		// save action
		function register_save(){
			// As like this, multi action name can be accepted
			$this->Transition->checkPrev(array(
				'register',
				'register_enquete',
				'register_confirm'
			));
			// mergedData() returns all session data saved on the actions merged
			if($this->User->saveAll($this->Transition->mergedData()){
				// clear all of session data TransitionComponent uses
				$this->Transition->clearData();
				$this->Session->setFlash(__('Register complete !!',true));
				$this->redirect(aa('action','index'));
			}else{
				$this->Session->setFlash(__('Register failed ...',true));
				$this->redirect(aa('action','register'));
			}
		}
	}

