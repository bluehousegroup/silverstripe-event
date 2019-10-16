<?php

namespace BluehouseGroup\Event;
use PageController;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\Map;
use SilverStripe\Dev\Debug;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Director;

class CalendarPageController extends PageController
{

    private static $allowed_actions = [
        'handleDate',
        'handlePeriod',
        'handleDateSegments',
        'handleDateRange',
        'handleSearch',
        'viewEvent',
        'viewOccurrence'
    ];

    private static $url_handlers = [
        'event//$URLSegment/$Date/$Time' => 'viewOccurrence',
        'event//$URLSegment/$Date' => 'viewOccurrence',
        'event//$URLSegment' => 'viewEvent',
        'date//$Year!/$Month/$Day' => 'handleDateSegments',
        'range//$Start/$End' => 'handleDateRange',
        'search//$Query!' => 'handleSearch',
        'period/$Period!' => 'handlePeriod',
        '' => 'index'
    ];

    public function index(HTTPRequest $r)
    {
        $skip = ($r->getVar('skip') ? intval($r->getVar('skip')) : 0);
        $eventDateTimes = $this->getAllEvents($skip);
        return $this->customise([
            'EventDateTimes' => $eventDateTimes
        ])->renderWith(['CalendarPage', 'Page']);
    }

    public function getCurrentPageURL() {
        $pageURL = 'http';
        if (Director::protocol() == 'https') {$pageURL .= "s";}
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

    public function viewOccurrence(HTTPRequest $r)
    {
        $url_segment = $r->param('URLSegment');
        $date = $r->param('Date');
        $time = $r->param('Time');

        $event = $this->getEventByURLSegment($url_segment);
        if (!$event) {
            return $this->httpError(404, 'Event not found');
        }

        $urlTime = ($time != NULL ? substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':00' : NULL);


//        Debug::show($date);
//        Debug::show($urlTime);
        $event_datetime = $event->getDateTime($date, $urlTime);

        if (!$event_datetime) {
            return $this->httpError(404, 'Event datetime not found');
        }

        $data = $this->customise([
            'Event' => $event,
            'EventDateTime' => $event_datetime,
            'MetaTitle' => $event->Title,
            'BackURL' => $this->getCurrentPageURL(),
        ]);

        $this->extend('onBeforeOccurrenceRender', $data);

        return $data->renderWith(['CalendarPage_event', 'Page']);
    }

    public function viewEvent(HTTPRequest $r)
    {
        $get_vars = $r->getVars();
        $url_segment = $r->param('URLSegment');
        $date = $date_times = null;
        $event = $this->getEventByURLSegment($url_segment);

        if (!$event) {
            return $this->httpError(404, 'Event not found');
        }

        if (isset($get_vars['date'])) {
            $date = $get_vars['date'];
            $date_times = $event->getDateTimes($date);

            if (empty($date_times->toArray())) {
                $date = null;
            }
        }

        $data = $this->customise([
            'Event' => $event,
            'Date' => $date,
            'DateTimes' => $date_times
        ]);

        $this->extend('onBeforeEventRender', $data);

        return $data->renderWith(['EventViewPage', 'Page']);
    }

    //calendar-page/search/query?StartDate=YYYYMMDD&EndDate=YYYYMMDD
    public function handleSearch(HTTPRequest $r){
        $query = $r->param('Query');
        $get_vars = $r->getVars();
        $start_date = $get_vars['StartDate'];
        $end_date = $get_vars['EndDate'];

        //TODO: Make this work without start/end date?
        //TODO: Make this work with Event Type
        $matchingEvents = $this->searchEvents($query, $start_date, $end_date);

        return $this->customise([
            'EventDateTimes' => $this->searchEvents($query),
            'SearchTerm' => $query,
            'DefaultDateHeader' => 'Events for keyword "' . $query . '"'
        ])->renderWith(['CalendarPage', 'Page']);
    }

    public function handlePeriod(HTTPRequest $r){

        $Period = $r->param('Period');
        $allowed_values = ['day', 'week', 'month'];

        if(!in_array($Period, $allowed_values)){
            return $this->httpError(404, 'Invalid value for period');
        }

        return $this->customise([
            'EventDateTimes' => $this->getEventDateTimes($Period),
            'SearchTerm' => $Period,
            'DefaultDateHeader' => 'Events for this ' . $Period
        ])->renderWith(['CalendarPage', 'Page']);

    }

    public function handleDateRange(HTTPRequest $r){

        $Start = $r->param('Start');
        $End = $r->param('End');
        //Values are YYYYMMDD in URL (no spaces or dashes)
        $dateRegex = '/^(19|20)[0-9]{2}(0|1)[0-9](0|1|2|3)[0-9]$/';
        $dateHeader = 'Viewing Events for ';

        if(preg_match($dateRegex, $Start) != 1){
            return $this->httpError(404, 'Invalid start date for range');
        } else {
            $rangeFilter['Start'] = substr($Start,0,4) . '-' . substr($Start,4,2) . '-' . substr($Start,6,2);
            $dateHeader .= date('F j, Y', strtotime($rangeFilter['Start']));
        }

        if($End){
            if(preg_match($dateRegex, $End) == 0){
                return $this->httpError(404, 'Invalid end date for range');
            } else {
                $rangeFilter['End'] = substr($End,0,4) . '-' . substr($End,4,2) . '-' . substr($End,6,2);
                $dateHeader .= ' to ' . date('F j, Y', strtotime($rangeFilter['End']));
            }
        }

        return $this->customise([
            'EventDateTimes' => $this->getEventDateTimes($rangeFilter),
            'DefaultDateHeader' => $dateHeader
        ])->renderWith(['CalendarPage', 'Page']);

    }

    public function handleDateSegments(HTTPRequest $r){

        $params = $r->params();
        $rangeFilter = [];
        $yearRegex = '/^[1,2][0-9]{3}$/';
        $dateMonthRegex = '/^[0-9]{2}$/';

        if($params['Year']){
            if(preg_match($yearRegex, $params['Year']) == 0){
                return $this->httpError(404, 'Invalid year format for search params');
            }
            $rangeFilter['Year'] = $params['Year'];
        }

        if ($params['Month']){
            if(preg_match($dateMonthRegex, $params['Month']) == 0){
                return $this->httpError(404, 'Invalid month format for search params');
            }
            $rangeFilter['Month'] = $params['Month'];
        }

        if ($params['Day']){
            if(preg_match($dateMonthRegex, $params['Month']) == 0){
                return $this->httpError(404, 'Invalid day format for search params');
            }
            $rangeFilter['Day'] = $params['Day'];
        }

        $dateHeader = 'Viewing Events for ';

        if(!is_null($params['Day']) && !is_null($params['Month'])){
            $dateString = $params['Year'] . '-' . $params['Month'] . '-' . $params['Day'];
            $dateHeader .= date('F j, Y', strtotime($dateString));
        } else if(is_null($params['Day']) && !is_null($params['Month'])) {
            $dateString = $params['Year'] . '-' . $params['Month'];
            $dateHeader .= date('F Y', strtotime($dateString));
        } else {
            $dateHeader .= ' ' . $params['Year'];
        }

        return $this->customise([
            'EventDateTimes' => $this->getEventDateTimes($rangeFilter),
            'DefaultDateHeader' => $dateHeader
        ])->renderWith(['CalendarPage', 'Page']);

    }

}
