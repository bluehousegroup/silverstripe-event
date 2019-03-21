<?php

namespace BluehouseGroup\Event;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextAreaField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Event extends DataObject
{
	private static $extensions = [
		Versioned::class
	];

	private static $table_name = 'Event';

	private static $db = [
		'Title' => 'Varchar(255)',
		'Description' => 'Text'
	];

	private static $has_many = [
		'EventDateTimes' => EventDateTime::class,
	];

	private static $has_one = [
		'Calendar' => CalendarPage::class,
	];	

	private static $summary_fields = [
		'Title' => 'Title',
		'StartTimeSummary' => 'Event Date/Time'
	];

	private static $searchable_fields = [
		'Title',
		'Description'
	];

	public function getStartTimeSummary()
	{
		$date = $this->EventDateTimes()->sort([
			'StartDate' => 'desc',
			'StartTime' => 'desc'
		])->first();

		return $date;
		
		//TODO: Why didn't this work?
		// return $date->StartDate . ' ' . $date->StartTime;

	}

	public function getResources()
	{
		return Resource::getByResourceType($this->SharePointTag);
	}

	public function getCMSFields()
	{
		$fields = FieldList::create(
			TabSet::create('Root',
				Tab::create('Main',
					TextField::create('Title',_t(__CLASS__ . '.TITLE', 'Title')),
					TextAreaField::create('Description',_t(__CLASS__ . '.DESCRIPTION', 'Description')),
					GridField::create(
						'EventDateTimes',
						_t(__CLASS__ . '.EventDateTimes', 'Occurrences'),
						$this->EventDateTimes(),
						$event_date_times_config = GridFieldConfig_RelationEditor::create()
					)
				)
			)
		);

		$event_date_times_config->getComponentByType(
			GridFieldDataColumns::class
		)->setDisplayFields	([
			'StartDate.nice' => 'Start Date',
			'StartTime.nice' => 'Start Time',
			'EndDate.nice' => 'End Date',
			'EndTime.nice' => 'End Time',
			'AllDay.nice' => 'All Day'		
		]);


		return $fields;
	}
}