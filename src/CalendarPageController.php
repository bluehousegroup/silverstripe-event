<?php

namespace BluehouseGroup\Event;
use PageController;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

use SilverStripe\ORM\DataList;

use SilverStripe\ORM\Map;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\Requirements;

class CalendarPageController extends PageController
{

    private static $allowed_actions = [
        'handleDate',
        'handlePeriod',
        'handleDateSegments',
        'handleDateRange',
        'handleSearch',
        'viewEvent'
    ];

    private static $url_handlers = [
        'event//$Id' => 'viewEvent',
        'date//$Year!/$Month/$Day' => 'handleDateSegments',
        'range//$Start!/$End' => 'handleDateRange',
        'search//$Query!' => 'handleSearch',
        '$Period!' => 'handlePeriod',
        '' => 'index'
    ];

    public function handleSearch(HTTPRequest $r){
        $query = $r->param('Query');
        $matchingEvents = $this->searchEvents($query);

        

        return $this->customise([
            'EventDateTimes' => $this->searchEvents($query),
            'SearchTerm' => $query
        ])->renderWith(['CalendarPage', 'Page']);      
    }

    public function viewEvent(HTTPRequest $r){
        $eventID = $r->param('Id');
        return $this->customise([
            'Event' => $this->getEvent($eventID)
        ])->renderWith(['EventViewPage', 'Page']);        

    }

    public function handlePeriod(HTTPRequest $r){
        $Period = $r->param('Period');
        $allowed_values = ['day', 'week', 'month'];

        if(!in_array($Period, $allowed_values)){
            return $this->httpError(404, 'Invalid value for period');
        }

        return $this->customise([
            'EventDateTimes' => $this->getEventDateTimes($Period),
            'SearchTerm' => $Period
        ])->renderWith(['CalendarPage', 'Page']);        

    }

    public function handleDateRange(HTTPRequest $r){

        $Start = $r->param('Start');
        $End = $r->param('End');
        //Based on YYYY-MM-DD format
        $dateRegex = '/^(19|20)[0-9]{2}(0|1)[0-9](0|1|2|3)[0-9]$/';

        if(preg_match($dateRegex, $Start) == 0){
            return $this->httpError(404, 'Invalid start date for range');
        } else {
            $rangeFilter['Start'] = substr($Start,0,4) . '-' . substr($Start,4,2) . '-' . substr($Start,6,2);
        }

        if($End){
            if(preg_match($dateRegex, $End) == 0){
                return $this->httpError(404, 'Invalid end date for range');
            } else {
                $rangeFilter['End'] = substr($End,0,4) . '-' . substr($End,4,2) . '-' . substr($End,6,2);
            }
        }

        return $this->customise([
            'EventDateTimes' => $this->getEventDateTimes($rangeFilter)
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

        return $this->customise([
            'EventDateTimes' => $this->getEventDateTimes($rangeFilter)
        ])->renderWith(['CalendarPage', 'Page']);

    }

    public function index(HTTPRequest $r){

        return $this->renderWith(['CalendarPage', 'Page']);

    }

}