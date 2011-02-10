# Transition Component #

## Version ##

This was versioned as 2.0 Beta.

## Introduction ##

Transition component is a CakePHP component to help your transitional pages logic.

- For instance, this bears most wizard parts.
- In almost every case, your method for action can be one-liner as like following codes:

		public function action(){
			$this->Transition->automate('previous_action', 'next_action');
		}

## Requirements ##

- CakePHP >= 2.0
- PHP >= 5.2.6

## Setup ##

	cd /path/to/root/app/plugins # or /path/to/root/plugins
	git clone git://github.com/hiromi2424/TransitionComponent.git transition

Or:

	cd /path/to/your_repository
	git submodule add git://github.com/hiromi2424/TransitionComponent.git plugins/transition


In controller's property section:
	public $components = array( ... , 'Transition.Transition');

## Summary ##

- checkData() is to check data(if given) with model validation and auto redirecting
- checkPrev() is to check previous page's session data exists.
- automate() is convenient method for checkData() and checkPrev().

## Sample ##

	class UsersController extends AppController{

		public $components = array('Transition');

		// base of user information
		public function register() {
			// give a next action name
			$this->Transition->checkData('register_enquete');
		}

		// input enquete
		public function register_enquete() {

			$this->Transition->automate(
				'register', // previous action to check
				'register_confirm', // next action
				'Enquete' // model name to validate
			);

		}

		// confirm inputs
		public function register_confirm() {

			$this->Transition->automate(
				'register_enquete', // prev
				'register_save', // next
				array(
					'validationMethod' => 'validateCaptcha', // virtual function to validate with captcha
				)
			 );

			$this->set('data', $this->Transition->allData());
			$this->set('captcha', createCaptcha()); // virtual function to create a captcha

		}

		// stroring inputs
		public function register_save() {

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
