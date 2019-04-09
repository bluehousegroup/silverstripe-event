<?php

namespace BluehouseGroup\Event;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
//use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows; //Not sure if this is needed

class CalendarPage extends SiteTree
{
	//For reference: https://github.com/unclecheese/silverstripe-event-calendar/blob/master/code/Calendar.php

	private static $table_name = 'Calendar';

	private static $db = [
		'DefaultDateHeader' => 'Varchar(50)',
		'OtherDatesCount' => 'Int',
		'RSSTitle' => 'Varchar(255)',
		'DefaultFutureMonths' => 'Int',
		'EventsPerPage' => 'Int',
		'DefaultView' => "Enum('today,week,month,weekend,upcoming','upcoming')"
	];

	private static $defaults = [
		'DefaultDateHeader' => 'Upcoming Events',
		'OtherDatesCount' => '3',
		'DefaultFutureMonths' => '6',
		'EventsPerPage' => '10',
		'DefaultView' => 'upcoming'
	];

  private static $has_many = [
		'Events' => Event::class
	];

	public function getAllEvents($skip = 0)
	{
		$event_ids = $this->Events()->map('ID', 'ID')->toArray();
		$eventDateTimes =  EventDateTime::get()->filter(['EventID' => $event_ids]);
		return $eventDateTimes->limit($this->EventsPerPage, $skip);
	}


	public function searchEvents($query, $start_date = null, $end_date = null, $skip = 0)
	{
		//TODO: Is more validation against the search term required?

		$event_ids = $this->Events()->filterAny([
			'Title:PartialMatch' => $query,
			'Description:PartialMatch' => $query
		])->map('ID', 'ID')->toArray();

		if (!empty($event_ids)) {
			$filters = ['EventID' => $event_ids, 'StartDate:GreaterThanOrEqual' => date('Y-m-d')];

			if ($start_date) {
				$filters['StartDate:GreaterThanOrEqual'] = date('Y-m-d', strtotime($start_date));
			}

			if ($end_date) {
				$filters['EndDate:LessThanOrEqual'] = date('Y-m-d', strtotime($end_date));
			}

			return EventDateTime::get()->filter($filters)->sort(['StartDate' => 'ASC', 'StartTime' => 'ASC'])->limit($this->EventsPerPage, $skip);
		} else {
			return false;
		}
	}

	public function getEvent($id)
	{
		return Event::get()->byId($id);
	}

	public function getEventByURLSegment($url_segment)
	{
		return Event::get()->filter(['URLSegment' => $url_segment])->first();
	}

	public function getEventDateTimes($filter = false, $skip = 0)
	{
		
		$eventIDs = $this->Events()->map('ID', 'ID')->toArray();

		if(!empty($eventIDs)){

		$eventFilter = [ 'EventID' => $eventIDs ];

		if(is_array($filter)){

			$Year = (array_key_exists('Year', $filter) ? $filter['Year'] : false);
			$Month = (array_key_exists('Month', $filter) ? $filter['Month'] : false);
			$Day = (array_key_exists('Day', $filter) ? $filter['Day'] : false);
			$Start = (array_key_exists('Start', $filter) ? $filter['Start'] : false);
			$End = (array_key_exists('End', $filter) ? $filter['End'] : false);

			switch($filter){
				case ($Year && !$Month && !$Day):
					$eventFilter['StartDate:StartsWith'] = "{$Year}";
					break;
				case ($Year && $Month && !$Day):
					$eventFilter['StartDate:StartsWith'] = "%{$Year}-{$Month}%";
					break;					
				case ($Year && $Month && $Day):
					$eventFilter['StartDate'] = "{$Year}-{$Month}-{$Day}";
					break;
				case ($Start && $End):
					$eventFilter['StartDate:GreaterThanOrEqual'] = $Start;
					$eventFilter['StartDate:LessThanOrEqual'] = $End;
					break;
				case ($Start && !$End):
					$eventFilter['StartDate:GreaterThanOrEqual'] = $Start;
					break;
			}

		} else {

			switch($filter){
				case 'day':
					$eventFilter = ['StartDate' => date("Y-m-d")];
					break;
				case 'week':
					$year = date('Y');
					$week = date('W');
					$monday = date('Y-m-d', strtotime("{$year}-W{$week}-1"));
					$sunday = date('Y-m-d', strtotime("{$year}-W{$week}-7"));
					$eventFilter['StartDate:GreaterThan'] = $monday;
					$eventFilter['StartDate:LessThan'] = $sunday;
					break;
				case 'month':
					$first = date('Y-m-01');
					$last = date('Y-m-t');
					$eventFilter['StartDate:GreaterThanOrEqual'] = $first;
					$eventFilter['StartDate:LessThanOrEqual'] = $last;
					break;									

			}

		}

		$eventDateTimes = EventDateTime::get()->filter($eventFilter)->sort(['StartDate' => 'ASC', 'StartTime' => 'ASC']);
		$limit = $eventDateTimes->limit($this->EventsPerPage, $skip);
		return $limit;
		
		} else {
			return false;
		}
	}

	public function getCMSFields() 
	{
		$self = $this;

		$this->beforeUpdateCMSFields(function($fields) use ($self) {

			$fields->addFieldsToTab("Root.Settings", [
				DropdownField::create('DefaultView',_t('Calendar.DEFAULTVIEW','Default view'), array (
					'upcoming' => _t('Calendar.UPCOMINGVIEW',"Show a list of upcoming events."),
					'month' => _t('Calendar.MONTHVIEW',"Show this month's events."),
					'week' => _t('Calendar.WEEKVIEW',"Show this week's events. If none, fall back on this month's"),
					'today' => _t('Calendar.TODAYVIEW',"Show today's events. If none, fall back on this week's events"),
					'weekend' => _t('Calendar.WEEKENDVIEW',"Show this weekend's events.")
				))->addExtraClass('defaultView'),
				NumericField::create('DefaultFutureMonths', _t('Calendar.DEFAULTFUTUREMONTHS','Number maximum number of future months to show in default view'))->addExtraClass('defaultFutureMonths'),
				NumericField::create('EventsPerPage', _t('Calendar.EVENTSPERPAGE','Events per page')),
				TextField::create('DefaultDateHeader', _t('Calendar.DEFAULTDATEHEADER','Default date header (displays when no date range has been selected)')),
				NumericField::create('OtherDatesCount', _t('Calendar.NUMBERFUTUREDATES','Number of future dates to show for repeating events'))
			]);

			$fields->addFieldToTab("Root.Settings", new TextField('RSSTitle', _t('Calendar.RSSTITLE','Title of RSS Feed')),'Content');

			//TODO add tab to manage Events
			$fields->addFieldToTab("Root.Events", 
				GridField::create(
					'Events',
					_t(__CLASS__ . '.Events', 'Events'),
					$this->Events(),
					$events_config = GridFieldConfig_RelationEditor::create()
				)->setTitle(
					'Manage events for calendar'
				)			
			);
		});

		$fields = parent::getCMSFields();

		return $fields;
	}

}
