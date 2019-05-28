<?php

namespace Dynamic\CoreEvents\Page;

use Dynamic\Core\Page\HolderPageController;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\PaginatedList;

/**
 * Class EventHolder_Controller
 */
class EventHolderController extends HolderPageController
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
