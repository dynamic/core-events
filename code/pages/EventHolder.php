<?php

class EventHolder extends HolderPage implements PermissionProvider
{
    public static $item_class = 'EventPage';
    private static $allowed_children = array('EventPage');
    private static $singular_name = 'Event Holder';
    private static $plural_name = 'Events Holder';
    private static $description = 'Page holding events, displays child pages that are events';

    private static $timezone = 'America/Chicago';

    private static $db = array(
        'ICSFeed' => 'Varchar(255)',
        'EventsPerPage' => 'Int',
        //0 == All
        'RangeToShow' => 'Enum("Month,Year,All Upcoming","Month")',
        //TODO add day option, bug in getFeedEvents date logic
    );

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

    public function getFeedEvents($start_date = null, $end_date = null)
    {
        $start = ($start_date !== null) ? $start_date : new DateTime($start_date);
        // single day views don't pass end dates
        $end = ($end_date !== null) ? $this->buildEndDate($end_date) : $this->buildEndDate();

        $feedReader = new ICSReader($this->ICSFeed);
        $events = $feedReader->getEvents();
        $feedEvents = new ArrayList();
        foreach ($events as $event) {
            // translate iCal schema into CalendarAnnouncement schema (datetime + title/content)
            $feedEvent = new EventPage();
            //pass ICS feed ID to event list
            $feedEvent->Title = $event['SUMMARY'];
            if (isset($event['DESCRIPTION'])) {
                $feedEvent->Content = $event['DESCRIPTION'];
            }

            if (!array_key_exists('DTEND', $event)) {
                $event['DTEND'] = $event['DTSTART'];
            }

            $startDateTime = $this->iCalDateToDateTime($event['DTSTART']);
            $endDateTime = $this->iCalDateToDateTime($event['DTEND']);

            if (($end != false) && (($startDateTime < $start && $endDateTime < $start)
                    || $startDateTime > $end && $endDateTime > $end)
            ) {
                // do nothing; dates outside range
            } else {
                if ($startDateTime->getTimestamp() >= $start->getTimestamp()) {
                    $feedEvent->Date = $startDateTime->format('Y-m-d');
                    $feedEvent->Time = $startDateTime->format('H:i:s');
                    $feedEvent->EndDate = $endDateTime->format('Y-m-d');
                    $feedEvent->EndTime = $endDateTime->format('H:i:s');
                    $feedEvents->push($feedEvent);
                }
            }
        }
        return $feedEvents;
    }

    public function iCalDateToDateTime($date)
    {
        $dt = new DateTime($date);
        $dt->setTimezone(new DateTimeZone($this->stat('timezone')));

        return $dt;
    }

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

    public function getEvents($filter = null, $limit = 10)
    {
        $eventList = ArrayList::create();
        $events = self::getUpcomingEvents($filter, $limit);
        $eventList->merge($events);
        if ($this->ICSFeed) {
            $icsEvents = $this->getFeedEvents();
            $eventList->merge($icsEvents);
        }

        return $eventList;
    }

    public function getItemsShort()
    {
        return EventPage::get()
            ->limit(3)
            ->filter(array(
                'Date:LessThan:Not' => date('Y-m-d', strtotime('now')),
                'ParentID' => $this->ID,
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

    public function canEdit($member = null)
    {
        return Permission::check('EventHolder_CRUD');
    }

    public function canDelete($member = null)
    {
        return Permission::check('EventHolder_CRUD');
    }

    public function canCreate($member = null)
    {
        return Permission::check('EventHolder_CRUD');
    }

    public function providePermissions()
    {
        return array(
            //'Location_VIEW' => 'Read a Location',
            'EventHolder_CRUD' => 'Create, Update and Delete an Event Holder Page',
        );
    }
}

class EventHolder_Controller extends HolderPage_Controller
{
    public function init()
    {
        parent::init();
    }

    private static $allowed_actions = array(
        'tag',
    );

    public function Items($filter = array(), $pageSize = 10)
    {
        $filter['ParentID'] = $this->Data()->ID;
        $class = $this->Data()->stat('item_class');

        $items = $this->getUpcomingEvents($filter);

        $list = PaginatedList::create($items, $this->request);
        $list->setPageLength($pageSize);

        return $list;
    }

    public function tag()
    {
        $request = $this->request;
        $params = $request->allParams();

        if ($tag = Convert::raw2sql(urldecode($params['ID']))) {
            $filter = array('Tags.Title' => $tag);

            return $this->customise(array(
                'Message' => 'showing entries tagged "' . $tag . '"',
                'Items' => $this->Items($filter),
            ));
        }

        return $this->Items();
    }

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
