<?php

/**
 * @property string Type
 * @property int RecurringNumber
 * @property string RecurringDay
 * @property string RecursionStart
 * @property string RecursionEnd
 * @property boolean MonthDate
 * @method ManyManyList|Event[] Event
 *
 * @todo disable "New Record" button from event when editing existing object
 */

class RecursionSchedule extends DataObject{

	private static $singular_name = 'Recursion Schedule';
	private static $plural_name = 'Recursion Schedules';
	private static $description = 'A recursion schedule for a particular event';

	private static $db = array(
		'Type' => 'Enum("Week,Month,Year")',
		'RecurringNumber' => 'Int',
		'RecurringDay' => 'Enum("Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday")',
		'RecursionStart' => 'Date',
		'RecursionEnd' => 'Date',
		'MonthDate' => 'Boolean'
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

		//@todo refactor cms fields to properly allow for recurring options based on recursion logic

		$fields->addFieldToTab(
			'Root.Main',
			DropdownField::create('Type')
				->setSource(singleton('RecursionSchedule')
					->dbObject('Type')
					->enumValues()
				)->setEmptyString('Select Recursion Type')
		);

		$fields->addFieldToTab(
			'Root.Main',
			$monthDate = CheckboxField::create('MonthDate')
				->setTitle('Monthly based on Recursion Start Date')
		);

		$ordinal = [];
		$start = 1;
		while($start <= 5){
			$ordinal[$start] = Ordinal::getNumToOrdinalWord($start);
			$start++;
		}

		$fields->addFieldToTab(
			'Root.Main',
			$recurringNumber = DropdownField::create('RecurringNumber')
				->setSource($ordinal)
				->setTitle('Every what iteration of your selected day?')
		);

		$fields->addFieldToTab(
			'Root.Main',
			$recurringDay = DropdownField::create('RecurringDay')
				->setTitle('On which weekday?')
				->setSource(
					singleton('RecursionSchedule')
						->dbObject('RecurringDay')
						->enumValues()
				)->setEmptyString('Select day of week')
		);

		$monthDate
			->displayIf('Type')->isEqualTo('Month');

		$recurringNumber
			->displayUnless('Type')->isEqualTo('Week')
			->orIf('Type')->isEqualTo('Year')
			->orIf('MonthDate')->isChecked();
		$recurringDay
			->displayUnless('Type')->isEqualTo('Year')
			->orIf('MonthDate')->isChecked();

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	/**
	 * Function that validates that
	 * the data for the RecursionSchedule
	 * is valid
	 *
	 * @return ValidationResult
	 *
	 * @Todo write validation for date field forcing it to be after the Event StartDate
	 */
	public function validate(){
		$result = parent::validate();

		/*if($this->Country == 'DE' && $this->Postcode && strlen($this->Postcode) != 5) {
			$result->error('Need five digits for German postcodes');
		}*/

		return $result;
	}

	/**
	 * This function determines a human readable
	 * string describing the recursion schedule
	 *
	 * @return string
	 *
	 * @todo Implement full logic for all scenarios available
	 */
	public function toString(){
		return $this->Type.": every ".$this->RecurringDay;
	}

}