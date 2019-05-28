<?php

namespace Dynamic\CoreEvents\Page;

use ICal\ICal;
use DateTime;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\Security\Permission;
use Dynamic\Core\Page\HolderPage;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Core\Convert;
use Dynamic\Core\Page\HolderPageController;

/**
 * Class EventHolder
 *
 * @property string $ICSFeed
 * @property int $EventsPerPage
 * @property string $RangeToShow
 */
class EventHolder extends HolderPage implements PermissionProvider
{

    /**
     * @var string
     */
    private static $item_class = EventPage::class;

    /**
     * @var array
     */
    private static $allowed_children = array(EventPage::class);

    /**
     * @var string
     */
    private static $singular_name = 'Event Holder';

    /**
     * @var string
     */
    private static $plural_name = 'Events Holder';

    /**
     * @var string
     */
    private static $description = 'Page holding events, displays child pages that are events';

    private static $table_name = 'EventHolder';

    /**
     * @var string
     */
    private static $timezone = 'America/Chicago';

    /**
     * @var array
     */
    private static $db = array(
        'ICSFeed'       => 'Varchar(255)',
        'EventsPerPage' => 'Int',
        //0 == All
        'RangeToShow'   => 'Enum("Month,Year,All Upcoming","Month")',
        //TODO add day option, bug in getFeedEvents date logic
    );

    private $event_data;

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root.Main', TextField::create('ICSFeed')->setTitle('ICS Feed URL'), 'Content');
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'RangeToShow',
                'Range to show',
                singleton(EventHolder::class)->dbObject('RangeToShow')->enumValues()
            ),
            'Content'
        );
        $fields->addFieldToTab(
            'Root.Main',
            NumericField::create(
                'EventsPerPage'
            )->setTitle('Events to show per page (0 shows all based on the "Rage to show")'),
            'Content'
        );

        return $fields;
    }

    /**
     * @param int $limit the number of weeks to fetch
     *
     * @return $this
     */
    public function setEventData($limit = 0)
    {
        $parser = new ICal(false, [
            'defaultTimeZone'       => 'America/Chicago',
            'useTimeZoneWithRRules' => true,
        ]);
        if ($limit > 0) {
            $parser->eventsFromInterval($limit);
        }
        $parser->initUrl($this->ICSFeed);

        $this->event_data = $parser;

        return $this;
    }

    /**
     * @param int $limit the number of weeks to fetch
     *
     * @return mixed
     */
    public function getEventData($limit = 0)
    {
        if (!$this->event_data) {
            $this->setEventData($limit);
        }

        return $this->event_data;
    }

    /**
     * @return Generator
     */
    protected function iterateEvents()
    {
        foreach ($this->getEventData()->eventsFromRange() as $data) {
            yield $data;
        }
    }

    /**
     * @param null $start_date
     * @param null $end_date
     * @param int $icsWeeks
     *
     * @return ArrayList
     */
    public function getFeedEvents($start_date = null, $end_date = null, $icsWeeks = 0)
    {
        $start = ($start_date !== null) ? $start_date : new DateTime($start_date);
        // single day views don't pass end dates
        $end = ($end_date !== null) ? $this->buildEndDate($end_date) : $this->buildEndDate($start);

        $parser = $this->getEventData($icsWeeks);

        $feedEvents = new ArrayList();
        foreach ($this->iterateEvents() as $event) {
            // translate iCal schema into CalendarAnnouncement schema (datetime + title/content)
            $feedEvent = new EventPage();
            //pass ICS feed ID to event list
            $feedEvent->Title = $event->summary;
            if ($event->description != null) {
                $feedEvent->Content = $event->description;
            }
            $startDateTime = $parser->iCalDateToDateTime($event->dtstart,
                true, true);
            $endDateTime = $parser->iCalDateToDateTime($event->dtend,
                true, true);
            $feedEvent->Date = $startDateTime->format('Y-m-d');
            $feedEvent->Time = $startDateTime->format('H:i:s');
            $feedEvent->EndDate = $endDateTime->format('Y-m-d');
            $feedEvent->EndTime = $endDateTime->format('H:i:s');
            $feedEvents->push($feedEvent);
        }

        return $feedEvents;
    }

    /**
     * @param null $start
     *
     * @return bool|DateTime|false|int|null|string
     */
    public function buildEndDate($start = null)
    {
        if ($start === null) {
            $start = new DateTime();
        }

        if ($start instanceof DateTime) {
            $start = $start->getTimestamp();
        }

        switch ($this->RangeToShow) {
            case 'Day':
                $end_date = $start;
                break;
            case 'Year':
                $end_date = date('Y-m-d', strtotime('+ 365 day', $start));
                break;
            case 'All Upcoming':
                $end_date = false;
                break;
            default:
                $end_date = date('Y-m-d', strtotime('+ 1 month', $start));
                break;
        }

        return $end_date;
    }

    /**
     * @param array $filter
     * @param int $limit
     *
     * @return DataList
     */
    public static function getUpcomingEvents($filter = array(), $limit = 10)
    {
        $filter['Date:GreaterThanOrEqual'] = date('Y-m-d', strtotime('now'));
        $events = ($limit == 0) ?
            EventPage::get()
                ->filter($filter)
                ->sort('Date', 'ASC')

            :
            EventPage::get()
                ->filter($filter)
                ->limit($limit)
                ->sort('Date', 'ASC');

        return $events;
    }

    /**
     * @param null $filter
     * @param int $limit
     * @param int $icsWeeks
     *
     * @return ArrayList
     */
    public function getEvents($filter = null, $limit = 10, $icsWeeks = 0)
    {
        $eventList = ArrayList::create();
        $events = self::getUpcomingEvents($filter, $limit);
        $eventList->merge($events);
        if ($this->ICSFeed) {
            $icsEvents = $this->getFeedEvents(null, null, $icsWeeks);
            $eventList->merge($icsEvents);
        }

        return $eventList;
    }

    /**
     * @return DataList
     */
    public function getItemsShort()
    {
        return EventPage::get()
            ->limit(3)
            ->filter(array(
                'Date:LessThan:Not' => date('Y-m-d', strtotime('now')),
                'ParentID'          => $this->ID,
            ))
            ->sort('Date', 'ASC');
    }

    /**
     * @param Member $member
     *
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        return parent::canView($member = null);
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canEdit($member = null, $context = [])
    {
        return Permission::check('EventHolder_CRUD');
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canDelete($member = null, $context = [])
    {
        return Permission::check('EventHolder_CRUD');
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canCreate($member = null, $context = [])
    {
        return Permission::check('EventHolder_CRUD');
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return array(
            //'Location_VIEW' => 'Read a Location',
            'EventHolder_CRUD' => 'Create, Update and Delete an Event Holder Page',
        );
    }

}
