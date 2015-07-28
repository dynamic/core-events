<?php

/**
 * Class Event
 * @package uw-union
 * @todo implement validation for db fields
 * @todo update getCMSFields() with appropriate tabs
 * @todo implement Location relation
 * @todo implement Venue relation
 *
 * @property string Title
 * @property string ShortDescription
 * @property string Description
 * @property string StartDate
 * @property string StartTime
 * @property string EndDate
 * @property string EndTime
 * @property boolean Recursion
 * @property string URLSegment
 * @property int SliderWidth
 * @property int SliderHeight
 * @property string Animation
 * @property boolean Loop
 * @property boolean Animate
 * @property boolean ThumbnailNav
 * @property int ImageID
 * @property int RecursionScheduleID
 * @property int ExceptionScheduleID
 * @method Image Image
 * @method RecursionSchedule RecursionSchedule
 * @method ExceptionSchedule ExceptionSchedule
 * @method DataList|SlideImage[] Slides
 * @method DataList|AdditionalDate[] AdditionalDates
 * @mixin FlexSlider
 */

class Event extends DataObject implements PermissionProvider{

	private static $singular_name = 'Event';
	private static $plural_name = 'Events';
	private static $description = 'An event to show on the event calendar';

	/**
	 * This static declares database columns
	 * for the Event DataObject
	 *
	 * @var array
	 */
	private static $db = array(
		'Title' => 'Varchar(255)',
		'Description' => 'HTMLText',
		'StartDate' => 'Date',
		'StartTime' => 'Time',
		'EndDate' => 'Date',
		'EndTime' => 'Time',
		'AllDay' => 'Boolean',
		'Recursion' => 'Boolean',
		'URLSegment' => 'Varchar(255)'
	);

	/**
	 * This static declares single relations
	 * and is represented in the database as
	 * KeyNameID from the array
	 *
	 * @var array
	 */
	private static $has_one = array(
		'Image' => 'Image',
		'RecursionSchedule' => 'RecursionSchedule',
		'ExceptionSchedule' => 'ExceptionSchedule'
	);

    /**
     * This static declares a many relation
     * to another Object or Page. The relation
     * is set on the reciprocating class via
     * a $has_one setting.
     *
     * @var array
     */
    private static $has_many = array(
        'AdditionalDates' => 'AdditionalDate'
    );
	private static $many_many = array();
	private static $many_many_extraFields = array();
	private static $belongs_many_many = array();

	private static $casting = array();
	private static $defaults = array();
	private static $default_sort = array();

	/**
	 * This static declares fields that
	 * will be visible in the GridField
	 * summary of the cms.
	 *
	 * @var array
	 */
	private static $summary_fields = array(
		'Title' => 'Title',
		'StartDate.Nice' => 'Start Date',
		'Recursion.Nice' => 'Recurring'
	);
	private static $searchable_fields = array();
	private static $field_labels = array();
	private static $indexes = array(
		'URLSegment' => true
	);

	/**
	 * This function builds the tab/field lists
	 * used in the cms to manage an Event's content
	 * and data
	 *
	 * @return FieldList
	 */
	public function getCMSFields(){
		$fields = parent::getCMSFields();

		$page = (EventHolder::get()->byID(Session::get('CMSMain.currentPage')))
			? EventHolder::get()->byID(Session::get('CMSMain.currentPage'))
			: EventHolder::get()->first();
        $baseLink = ($page)
            ? Controller::join_links(
			    Director::absoluteBaseURL(),
			    $page->RelativeLink(true),
			    'event/')
		    : '/event/';
		$urlsegment = new SiteTreeURLSegmentField("URLSegment", $this->fieldLabel('URLSegment'));
		$urlsegment->setURLPrefix($baseLink);
		$fields->addFieldToTab('Root.Main', $urlsegment);

		$fields->addFieldToTab(
			'Root.EventSettings',
			DateField::create('StartDate')
				->setTitle('Event Start Date')
		);
		$fields->addFieldToTab(
			'Root.EventSettings',
			TimePickerField::create('StartTime')
				->setTitle('Event Start Time')
		);
		$fields->addFieldToTab(
			'Root.EventSettings',
			DateField::create('EndDate')
				->setTitle('Event End Date')
		);
		$fields->addFieldToTab(
			'Root.EventSettings',
			TimePickerField::create('EndTime')
				->setTitle('Event End Time')
		);

		$image = new UploadField('Image', 'Image');
		$image->getValidator()->allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
		$image->setFolderName('Uploads/Events');
		$image->setConfig('allowedMaxFileNumber', 1);

		$fields->addFieldToTab(
			'Root.EventImages',
			$image
		);

		if($this->RecursionSchedule()->exists()){
			$fields->addFieldsToTab("Root.Main", array(
				ReadonlyField::create("add", "Recursion Schedule", $this->RecursionSchedule()->toString())
			));
		}
		$fields->removeByName("RecursionScheduleID");
		$fields->addFieldToTab("Root.Main",
			HasOneButtonField::create("RecursionSchedule", "Recursion Schedule", $this) //here!
		);

		if($this->ExceptionSchedule()->exists()){
			$fields->addFieldsToTab("Root.Main", array(
				ReadonlyField::create("add", "Exception Schedule", $this->ExceptionSchedule()->toString())
			));
		}
		$fields->removeByName("ExceptionScheduleID");
		$fields->addFieldToTab("Root.Main",
			HasOneButtonField::create("ExceptionSchedule", "Exception Schedule", $this) //here!
		);

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	/**
	 * This function allows the validation of event data
	 * on Save
	 *
	 * @return ValidationResult
	 *
	 * @todo determine required fields
	 * @todo implement required fields validation
	 */
	public function validate(){
		$result = parent::validate();

		if(!$this->Title) {
			$result->error('A Title is required before you can save an event');
		}

		if(!$this->StartDate) {
			$result->error('A Start Date is required before you can save an event');
		}

		if(!$this->StartTime) {
			$result->error('A Start Time is required before you can save an event');
		}

		return $result;
	}

	public function onBeforeWrite(){
		if(!$this->URLSegment){
			$siteTree = singleton('SiteTree');
			$this->URLSegment = $siteTree->generateURLSegment($this->Title);
		}
		parent::onBeforeWrite();
	}

	public function onBeforeDelete(){

        $delete = function($object){
            $object->delete();
        };

		//Remove the related recursion schedule to keep the database clean
		if($this->RecursionSchedule()->exists()) { $recursion = $this->RecursionSchedule(); $recursion->delete(); }
		//Remove the related recursion exceptions to keep the database clean
		if($this->ExceptionSchedule()->exists()) { $exception = $this->ExceptionSchedule(); $exception->delete(); }
        //Remove AdditionalDates to keep database clean
        if($this->AdditionalDates()->exists()) { $this->AdditionalDates()->each($delete); }

		parent::onBeforeDelete();
	}

	/**
	 * This function determines a user's
	 * ability to create a new event
	 *
	 * @param null $member
	 * @return bool|int
	 */
	public function canCreate($member = null){
		return Permission::check('CREATE_EVENT');
	}

	/**
	 * This function determines a user's
	 * ability to edit an existing event
	 *
	 * @param null $member
	 * @return bool|int
	 */
	public function canEdit($member = null){
		return Permission::check('EDIT_EVENT');
	}

	/**
	 * This function determines a user's
	 * ability to delete an existing event
	 *
	 * @param null $member
	 * @return bool|int
	 */
	public function canDelete($member = null){
		return Permission::check('DELETE_EVENT');
	}

	/**
	 * This function determines a user's
	 * ability to view an existing event
	 *
	 * @param null $member
	 * @return bool
	 */
	public function canView($member = null){
		return true;
	}

	/**
	 * This function provides permissions options
	 * within the security section of the cms
	 * for the Event object. The array keys are
	 * referenced when executing the can() function above
	 *
	 * @return array
	 */
	public function providePermissions() {
		return array(
			"CREATE_EVENT" => "Create Event",
			"EDIT_EVENT" => "Edit Event",
			"DELETE_EVENT" => "Delete Event"
		);
	}

	/**
	 * Returns a link to the edit form in model admin
	 * so those with access can easily get to the edit
	 * form if they notice an content issue on the frontend
	 *
	 * @access public
	 * @return string
	 * @link http://api.silverstripe.org/3.1/class-Controller.html#_join_links
	 *
	 */
	public function getLink(){
		return Controller::join_links(Controller::curr()->Link(), 'event', $this->URLSegment);
	}

	/**
	 * This function sets an array with recursion type,
	 * rules and exceptions for later use.
	 *
	 * @param Event $event
	 * @return array|bool
	 */
	public function getRecursionPattern(Event $event = null){
		$event = ($event) ? $event : $this;
		if(!$event->Recursion) return false;

		$recursion = $event->RecursionSchedule();
		$exception = $event->ExceptionSchedule();

		$schedule = array(
			'Type' => $recursion->Type,
			'NumberOffset' => $recursion->RecurringNumber,
			'WeekDay' => $recursion->RecurringDay,
			'StartingOn' => $recursion->RecursionStart,
			'EndingOn' => $recursion->RecursionEnd,
			'ExceptInstanceNumber' => $exception->InstanceNumber,
			'ExceptHolidays' => $exception->Holidays,
			'MonthDate' => $recursion->MonthDate
		);

		return $schedule;
	}

}