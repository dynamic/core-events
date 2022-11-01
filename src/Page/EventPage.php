<?php

namespace Dynamic\CoreEvents\Page;

use SheaDawson\TimePickerField\TimePickerField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\TimeField;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\Security\Permission;
use Dynamic\Core\Page\DetailPage;
use SilverStripe\Security\PermissionProvider;


class EventPage extends DetailPage implements PermissionProvider
{
    /**
     * @var string
     */
    private static $singular_name = 'Event';
    
    /**
     * @var string
     */
    private static $plural_name = 'Events';

    /**
     * @var string
     */
    private static $description = 'Event page with a Date and Time';

    /**
     * @var string[]
     */
    private static $db = [
        'Date' => 'Date',
        'EndDate' => 'Date',
        'Time' => 'Time',
        'EndTime' => 'Time',
    ];

    /**
     * @var int[]
     */
    private static $defaults = [
        'ShowInMenus' => 0,
    ];

    /**
     * @var string
     */
    private static $table_name = 'EventPage';

    /**
     * @return \Dynamic\Core\Page\FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        //DateField::set_default_config('showcalendar',true);

        $fields->addFieldToTab('Root.EventInformation', DateField::create('Date')->setTitle('Event Start Date'));
        $fields->addFieldToTab('Root.EventInformation', DateField::create('EndDate')->setTitle('Event End Date'));
        $fields->addFieldToTab('Root.EventInformation', TimeField::create('Time')->setTitle('Event Time'));
        $fields->addFieldToTab('Root.EventInformation', TimeField::create('EndTime')->setTitle('Event End Time'));//*/

        return $fields;
    }

    /**
     * @return \SilverStripe\ORM\ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();

        if ($this->EndTime && ($this->Time > $this->EndTime)) {
            return $result->addError('End Time must be later than the Start Time');
        }

        if ($this->EndDate && ($this->Date > $this->EndDate)) {
            return $result->addError('End Date must be equal to the Start Date or in the future');
        }

        return $result;
    }

    /**
     * @return void
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->EndDate) {
            $this->EndDate = $this->Date;
        }
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null, $context = [])
    {
        return parent::canView($member = null);
    }

    /**
     * @param $member
     * @param $context
     * @return bool|int
     */
    public function canEdit($member = null, $context = [])
    {
        return Permission::check('Event_CRUD');
    }

    /**
     * @param $member
     * @param $context
     * @return bool|int
     */
    public function canDelete($member = null, $context = [])
    {
        return Permission::check('Event_CRUD');
    }

    /**
     * @param $member
     * @param $context
     * @return bool|int
     */
    public function canCreate($member = null, $context = [])
    {
        if ($canCreate = Permission::check('Event_CRUD')) {
            return parent::canCreate($member, $context);
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function providePermissions()
    {
        return [
            //'Location_VIEW' => 'Read a Location',
            'Event_CRUD' => 'Create, Update and Delete a Event Page',
        ];
    }

}
