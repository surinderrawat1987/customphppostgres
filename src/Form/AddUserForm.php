<?php

namespace Surin\Test\Form;

class AddUserForm implements FormI {

    private $form,$fieldset,$firstname,$lastname,$email;
    function __construct($name,$method,$action,$class){
        // Instantiate the HTML_QuickForm2 object
        $this->form = new \HTML_QuickForm2($name,$method,['action'=>$action,'class' => $class]);        
        \HTML_QuickForm2_Factory::registerRule(
            'uniqueEmail', 'HTML_QuickForm2_Rule_Callback', null,
            array('callback' => ['\Surin\Test\Helper\DatabaseHelper','checkUniqueEmail'])
        );
        $this->addElements()->addFilterAndValidators();
    }

    private function addElements(){
        $this->fieldset = $this->form->addElement('fieldset');
        $this->firstname = $this->fieldset->addElement('text', 'firstname', array('size' => 50, 'maxlength' => 255))
                        ->setLabel('Enter your Firstname:');
        $this->lastname = $this->fieldset->addElement('text', 'lastname', array('size' => 50, 'maxlength' => 255))
                        ->setLabel('Enter your Lastname:');
        $this->email = $this->fieldset->addElement('text', 'email', array('size' => 50, 'maxlength' => 255))
                        ->setLabel('Enter your email:');
        $this->fieldset->addElement('submit', null, array('value' => 'Submit'));
        return $this;
    }

    private function addFilterAndValidators(){
        // Define filters and validation rules
        $this->firstname->addFilter('trim');
        $this->firstname->addRule('required', 'Please enter your first name*');

        $this->lastname->addFilter('trim');
        $this->lastname->addRule('required', 'Please enter your last name*');

        $this->email->addFilter('trim');
        $this->email->addRule('required', 'Please enter your email*');
        $this->email->addRule('email', 'Please enter correct email');
        $this->email->addRule('uniqueEmail', 'Email already exists !', array($this->email->getValue()));
        return $this;
    }

    public function getForm(){
        return $this->form;
    }

}