<?php

class EventHolder extends HolderPage
{

    public static $item_class = 'Event';
    private static $allowed_children = array('EventPage');
    private static $singular_name = 'Event Holder';
    private static $plural_name = 'Events Holder';
    private static $description = 'Page holding events, displays child pages that are events';

    private static $timezone = 'America/Chicago';

    private static $db = array(
        'ICSFeed' => 'Varchar(255)',
        'EventsPerPage' => 'Int',//0 == All
        'RangeToShow' => 'Enum("Month,Year,All Upcoming","Month")'//TODO add day option, bug in getFeedEvents date logic
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
        $start = sfDate::getInstance(strtotime('now'));
        $end_date = $this->buildEndDate($start);

        // single day views don't pass end dates
        if ($end_date) {
            $end = sfDate::getInstance($end_date);
        } else {
            $end = false;
        }

        $feedevents = new ArrayList();
        $feedreader = new ICSReader($this->ICSFeed);
        $events = $feedreader->getEvents();
        foreach ($events as $event) {
            // translate iCal schema into CalendarAnnouncement schema (datetime + title/content)
            $feedevent = new EventPage;
            $feedevent->Title = $event['SUMMARY'];
            if (isset($event['DESCRIPTION'])) {
                $feedevent->Content = $event['DESCRIPTION'];
            }

            $startdatetime = $this->iCalDateToDateTime($event['DTSTART']);
            $enddatetime = $this->iCalDateToDateTime($event['DTEND']);

            if (($end !== false) && (($startdatetime->get() < $start->get() && $enddatetime->get() < $start->get())
                    || $startdatetime->get() > $end->get() && $enddatetime->get() > $end->get())
            ) {
                // do nothing; dates outside range
            } else {
                if ($startdatetime->get() > $start->get()) {
                    $feedevent->Date = $startdatetime->format('Y-m-d');
                    $feedevent->Time = $startdatetime->format('H:i:s');

                    $feedevent->EndDate = $enddatetime->format('Y-m-d');
                    $feedevent->EndTime = $enddatetime->format('H:i:s');

                    $feedevents->push($feedevent);
                }
            }
        }
        return $feedevents;
    }

    public function iCalDateToDateTime($date)
    {
        date_default_timezone_set($this->stat('timezone'));
        $date = str_replace('T', '', $date);//remove T
        $date = str_replace('Z', '', $date);//remove Z
        $date = strtotime($date);
        $date = $date + date('Z');
        return sfDate::getInstance($date);
    }

    public function buildEndDate($start = null)
    {
        if ($start === null) {
            $start = sfDate::getInstance(strtotime('now'));
        }

        switch ($this->RangeToShow) {
            case 'Day':
                $end_date = $start;
                break;
            case 'Year':
                $end_date = date('Y-m-d', strtotime(date("Y-m-d", time()) . " + 365 day"));
                break;
            case 'All Upcoming':
                $end_date = false;
                break;
            default:
                $end_date = date('Y-m-d', strtotime(date("Y-m-d", time()) . " + 1 month"));
                break;
        }
        return $end_date;
    }

    /**
     * Function that gets any event,
     * past or future with recursion
     * turned on
     *
     * @param array $filter
     * @return ArrayList
     *
     * @Todo incorporate category filtering
     */
    public static function getUpcomingRecurringEventList($filter = array())
    {
        $filter['Recursion'] = true;

        return Event::get()
            ->filter($filter)
            ->filterByCallback(
                function ($item, $list) {
                    return (
                        (($item->RecursionSchedule()->RecursionEnd)
                            && (date('Y-m-d', strtotime($item->RecursionSchedule()->RecursionEnd)) >= date('Y-m-d')))
                        || !$item->RecursionSchedule()->RecursionEnd
                    );
                }
            );
    }

    /**
     * Function that get's a recurring
     * event's next date. This does not
     * overwrite the original StartDate
     * but rather updates it at run time.
     *
     * @param ArrayList $events
     * @return ArrayList
     */
    public static function getUpdatedRecurringEventsList(ArrayList $events)
    {

        $updatedEvents = ArrayList::create();

        $firstRun = true;

        /**
         * Closure that determines
         * next occurrence of a
         * recurring event.
         *
         * @param Event $event
         * @uses ArrayList
         * @uses
         *
         * @return ArrayList
         *
         * @Todo allow for specifying number of occurrences to include
         * @Todo consolidate code to not repeat itself
         */
        $getStartDate = function ($event) use ($updatedEvents, &$getStartDate, &$modifierNumber, &$firstRun) {

            /**
             * Closure that increments month,
             * incrementing year and wrapping month back to
             * 1 if is 12
             */
            $incrementMonthYear = function () use (&$month, &$year, &$increment) {
                $increment++;
                if ($month < 12) {
                    $month++;
                } else {
                    $month = 1;
                    $year++;
                }
            };

            $incrementYearUp = function () use (&$dateYear) {
                return $dateYear++;
            };

            $pattern = $event->getRecursionPattern();//get the recursion/exclusion params
            $month = date('n');
            $year = date('Y');

            //is this recursion expired
            //todo move this check to the ::get() of the events
            if (isset($pattern['EndingOn']) && date('Y-m-d') > date('Y-m-d', strtotime($pattern['EndingOn']))) {
                //don't add it to the list, next event please
            } else {

                //set our increment var
                $numberOffset = $pattern['NumberOffset'];
                $increment = 0;

                //check if holidays are excluded and set that flag
                if ($pattern['ExceptHolidays']) {
                    $holidaysObject = new Holidays();
                    $holidaysObject->setObservances(false);
                    $holidays = $holidaysObject->getHolidays();//get holidays array
                }

                //let's figure out if we need to do day of week, number day in month, or yearly recursion
                $type = $pattern['Type'];

                if ($type == 'Week') {
                    //get the day of each week it should recur on
                    $weekDay = $pattern['WeekDay'];

                    //check if today matches the weekly recursion
                    if (date('l') == $weekDay && date('Y-m-d') >= date('Y-m-d', strtotime($pattern['StartingOn']))) {
                        $date = date('Y-m-d');
                    } else {//if it's not today, get the next instance
                        $date = date('Y-m-d', strtotime("next {$weekDay}"));
                        //if in past increment month
                        while ($date < date('Y-m-d')) {
                            $increment++;
                            if($numberOffset != 0){
                                if ($increment % $numberOffset == 0) {
                                    continue;
                                }
                            }
                            $date = date('Y-m-d', strtotime("next {$weekDay} + $increment weeks"));
                        }
                        if (isset($holidays)) {
                            while (in_array($date, $holidays)) {
                                $increment++;
                                if($numberOffset != 0){
                                    if ($increment % $numberOffset == 0) {
                                        continue;
                                    }
                                }
                                $date = date('Y-m-d', strtotime("next {$weekDay} + $increment weeks"));
                            }
                        }
                    }
                } elseif ($type == 'Month') {

                    //get numbered weekday (xth thursday of each month)
                    if (isset($pattern['WeekDay']) && !$pattern['MonthDate']) {
                        $weekDay = $pattern['WeekDay'];
                        $numberOffset = $pattern['NumberOffset'];
                        $numberOffsetText = Ordinal::getNumToOrdinalWord($numberOffset);//textual representation of the ordinal

                        $origin = date('Y-m-d', strtotime("$numberOffsetText $weekDay of this month"));

                        //if it's today or still in the month, it's good
                        if ($origin >= date('Y-m-d') && (!isset($holidays) || !in_array($origin, $holidays))) {
                            $date = $origin;
                        } else {//if it's passed need to increment to next month
                            $incrementMonthYear();//increment month (and year if needed, wrapping month back to 1)

                            $monthWord = date('F', strtotime('1-' . $month . "-" . $year));

                            $date = date('Y-m-d', strtotime("$numberOffsetText $weekDay $monthWord $year"));

                            if($numberOffset != 0){
                                while (
                                    ($date < date('Y-m-d'))
                                    || (isset($holidays)
                                        && in_array($date, $holidays))
                                    || ($increment % $numberOffset == 0)
                                ) {
                                    $incrementMonthYear();
                                    $date = date('Y-m-d', strtotime("$numberOffsetText $weekDay $monthWord $year"));
                                }
                            }else{
                                while (
                                    ($date < date('Y-m-d'))
                                    || (isset($holidays)
                                        && in_array($date, $holidays))
                                ) {
                                    $incrementMonthYear();
                                    $date = date('Y-m-d', strtotime("$numberOffsetText $weekDay $monthWord $year"));
                                }
                            }

                        }
                    } else {//get the date of each month based on recursion start date

                        $day = date('j', strtotime($pattern['StartingOn']));

                        $date = date('Y-m-d', strtotime("{$day}-{$month}-{$year}"));//get recursion start date

                        //if recursion start date is in future, return that date
                        if($numberOffset != 0){
                            while (
                                $date < date('Y-m-d')//date is in past
                                || (isset($holidays) && in_array($date, $holidays))//date is holiday and should be excluded
                                || ($increment % $pattern['ExceptInstanceNumber'] == 0)//
                            ) {//is a numeric exception (every x except for x + nth instance) TODO test this line <<<
                                $incrementMonthYear();
                                $date = date('Y-m-d', strtotime("{$day}-{$month}-{$year}"));
                            }
                        }else{
                            while (
                                $date < date('Y-m-d')//date is in past
                                || (isset($holidays) && in_array($date, $holidays))
                            ) {
                                $incrementMonthYear();
                                $date = date('Y-m-d', strtotime("{$day}-{$month}-{$year}"));
                            }
                        }


                    }

                } else {//increment yearly on event date
                    $dateYear = date('Y', strtotime($event->StartDate));
                    $dateMonthDay = date('d-m', strtotime($event->StartDate));
                    $date = date('Y-m-d', strtotime("{$dateMonthDay}-$dateYear"));

                    while ($date < date('Y-m-d')) {
                        $incrementYearUp($dateYear);
                        $date = date('Y-m-d', strtotime("{$dateMonthDay}-$dateYear"));
                    }
                }
            }

            $event->StartDate = $date;
            $updatedEvents->push($event);
        };

        $events->each($getStartDate);

        return $updatedEvents;

    }

    public static function getUpcomingEvents($filter = array(), $limit = 10)
    {

        $recurringEvents = self::getUpcomingRecurringEventList();

        $recurringWithDates = self::getUpdatedRecurringEventsList($recurringEvents);

        $eventList = ArrayList::create();

        $eventPush = function ($event) use ($eventList) {
            $eventList->push($event);
        };

        if (empty($filter)) {
            $filter = array(
                'Date:GreaterThanOrEqual' => date('Y-m-d', strtotime('now')),
                'Recursion' => false
            );
        }
        $events = ($limit == 0) ?
            Event::get()
                ->filter($filter)
                ->sort('Date', 'ASC')

            :
            Event::get()
                ->filter($filter)
                ->limit($limit)
                ->sort('Date', 'ASC');

        $additionalDates = ArrayList::create();

        $generateAdditions = function ($addition) use ($additionalDates) {
            $newEvent = Event::create();
            $newEvent->Title = ($addition->Title) ? $addition->Title : $addition->Event()->Title;
            $newEvent->StartDate = $addition->Date;
            $newEvent->StartTime = $addition->StartTime;
            $newEvent->EndTime = $addition->EndTime;
            $newEvent->ShortDescription = ($addition->ShortDescription) ? $addition->ShortDescription : $addition->Event()->ShortDescription;
            $newEvent->Description = ($addition->Description) ? $addition->Description : $addition->Event()->Description;
            $newEvent->URLSegment = Controller::join_links($addition->Event()->URLSegment, $addition->ID);
            $additionalDates->push($newEvent);
        };

        $additionalEventDates = AdditionalDate::get()
            ->filter(array(
                'Date:GreaterThanOrEqual' => date('Y-m-d')
            ));

        $additionalEventDates->each($generateAdditions);

        $events->each($eventPush);
        $recurringWithDates->each($eventPush);
        $additionalDates->each($eventPush);

        return $eventList->sort('StartDate');

    }

    public function getEvents($filter = array(), $limit = 10)
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
        return self::getUpcomingEvents(null, 3);
    }

    /**
     * @param Member $member
     * @return boolean
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
            'EventHolder_CRUD' => 'Create, Update and Delete an Event Holder Page'
        );
    }

}

class EventHolder_Controller extends HolderPage_Controller
{

    private static $allowed_actions = array(
        'event'
    );

    public function init()
    {
        parent::init();

    }

    public function items($filter = array(), $pageSize = 10)
    {
        return $this->getUpcomingEvents();
    }

    public function getUpcomingEvents($paginate = true)
    {
        $pageSize = ($this->data()->EventsPerPage == 0) ? 10 : $this->data()->EventsPerPage;

        $filter = array(
            'EndDate:GreaterThanOrEqual' => date('Y-m-d', strtotime('now')),
            'ParentID' => $this->data()->ID
        );
        if ($this->data()->RangeToShow != 'All Upcoming') {
            $end_date = $this->data()->buildEndDate();
            $filter['Date:LessThanOrEqual'] = $end_date;
        }
        $items = $this->data()->getEvents($filter, 0);

        $newItems = $items->sort(
            array(
                'Date' => 'ASC',
                'Time' => 'ASC'
            )
        );

        //debug::show($newItems);
        if ($paginate === true) {
            $list = PaginatedList::create($newItems, $this->request);
            $list->setPageLength($pageSize);
        } else {
            $list = $newItems;
        }


        return $list;
    }

    /**
     * This function returns an Event DataObject
     * to be rendered with the specified Layout
     *
     * @param SS_HTTPRequest $request
     * @return HTMLText
     *
     * @todo renderWith appropriate layout
     */
    public function event(SS_HTTPRequest $request)
    {
        $event = Event::get()->filter('URLSegment', $request->param('ID'))->first();
        if ($request->param('OtherID')) {
            $event = $event->AdditionalDates()->byID($request->params('OtherID'));
        }
        return $event->renderWith(array('Page'));
    }

}