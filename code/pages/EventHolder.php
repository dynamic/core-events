<?php

use ICal\ICal;

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
    public static $item_class = 'EventPage';

    /**
     * @var array
     */
    private static $allowed_children = array('EventPage');

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
                singleton('EventHolder')->dbObject('RangeToShow')->enumValues()
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
                $end_date = date('Y-m-d', strtotime($start . ' + 365 day'));
                break;
            case 'All Upcoming':
                $end_date = false;
                break;
            default:
                $end_date = date('Y-m-d', strtotime($start . ' + 1 month'));
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
    public function canView($member = null)
    {
        return parent::canView($member = null);
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canEdit($member = null)
    {
        return Permission::check('EventHolder_CRUD');
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canDelete($member = null)
    {
        return Permission::check('EventHolder_CRUD');
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canCreate($member = null)
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

/**
 * Class EventHolder_Controller
 */
class EventHolder_Controller extends HolderPage_Controller
{

    /**
     *
     */
    public function init()
    {
        parent::init();
    }

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'tag',
    );

    /**
     * @param array $filter
     * @param int $pageSize
     *
     * @return PaginatedList
     */
    public function Items($filter = array(), $pageSize = 10)
    {
        $filter['ParentID'] = $this->Data()->ID;
        $class = $this->Data()->stat('item_class');

        $items = $this->getUpcomingEvents($filter);

        $list = PaginatedList::create($items, $this->request);
        $list->setPageLength($pageSize);

        return $list;
    }

    /**
     * @return PaginatedList|ViewableData_Customised
     */
    public function tag()
    {
        $request = $this->request;
        $params = $request->allParams();

        if ($tag = Convert::raw2sql(urldecode($params['ID']))) {
            $filter = array('Tags.Title' => $tag);

            return $this->customise(array(
                'Message' => 'showing entries tagged "' . $tag . '"',
                'Items'   => $this->Items($filter),
            ));
        }

        return $this->Items();
    }

    /**
     * @param array $filter
     *
     * @return mixed
     */
    public function getUpcomingEvents($filter = array())
    {
        $pageSize = ($this->data()->EventsPerPage == 0) ? 10 : $this->data()->EventsPerPage;

        $filter['EndDate:GreaterThanOrEqual'] = date('Y-m-d', strtotime('now'));
        if ($this->data()->RangeToShow != 'All Upcoming') {
            $end_date = $this->data()->buildEndDate();
            $filter['Date:LessThanOrEqual'] = $end_date;
        }
        $items = $this->data()->getEvents($filter, 0);

        return $items->sort(array(
            'Date' => 'ASC',
            'Time' => 'ASC',
        ));
    }

}
