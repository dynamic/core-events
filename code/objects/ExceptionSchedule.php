<?php

/**
 * @property int InstanceNumber
 * @property boolean Holidays
 * @method ManyManyList|Event[] Event
 *
 * @todo disable "New Record" button from event when editing existing object
 */

class ExceptionSchedule extends DataObject{

	private static $singular_name = 'Exception Schedule';
	private static $plural_name = 'Exception Schedules';
	private static $description = 'Exception to an events recursion schedule';

	private static $db = array(
		'InstanceNumber' => 'Int',
		'Holidays' => 'Boolean'//need to get these
	);
	private static $has_one = array();
	private static $belongs_to = array(
		'Event' => 'Event'
	);
	private static $has_many = array();
	private static $many_many = array();
	private static $many_many_extraFields = array();
	private static $belongs_many_many = array();

	private static $casting = array();
	private static $defaults = array();
	private static $default_sort = array();


	private static $summary_fields = array();
	private static $searchable_fields = array();
	private static $field_labels = array();
	private static $indexes = array();

	public function getCMSFields(){
		$fields = parent::getCMSFields();



		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	public function validate(){
		$result = parent::validate();

		/*if($this->Country == 'DE' && $this->Postcode && strlen($this->Postcode) != 5) {
			$result->error('Need five digits for German postcodes');
		}*/

		return $result;
	}

	/**
	 * This function determines a human readable
	 * string describing the exception schedule
	 *
	 * @return string
	 *
	 * @todo Implement elseif logic
	 */
	public function toString(){

		if($this->InstanceNumber && $this->Holidays){
			$number = Ordinal::getOrdinalSuffix($this->InstanceNumber);
			$exception = "Except every {$number} instance and holidays";
		}elseif($this->InstanceNumber || $this->Holidays){
			if($this->InstanceNumber){
				$number = Ordinal::getOrdinalSuffix($this->InstanceNumber);
				$exception = "Except every $number instance";
			}else{
				$exception = "Except holidays";
			}
		}else{
			$exception = 'No data entered for exception';
		}
		return $exception;
	}

}