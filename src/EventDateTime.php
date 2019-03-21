<?php

namespace BluehouseGroup\Event;

use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\TimeField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Versioned\Versioned;


class EventDateTime extends DataObject
{
	private static $extensions = [
		Versioned::class
	];

	private static $singular_name = 'Occurrence';
	private static $plural_name = 'Occurrences';

	private static $table_name = 'EventDateTime';

	private static $db = [
		'StartDate' => 'Date',
		'StartTime' => 'Time',
		'EndDate' => 'Date',
		'EndTime' => 'Time',
		'AllDay' => 'Boolean'
	];

	private static $defaults = [
		'StartDate' => "",
		'StartTime' => '',
		'EndDate' => '',
		'EndTime' => ''
	];

	public function  validate(){

		$result = parent::validate();

		if($this->StartTime && $this->EndTime){
			$startTime = date("$this->StartDate $this->StartTime");
			$endTime = ($this->EndDate ? "$this->EndDate $this->EndTime" : "$this->StartDate $this->EndTime");
			if($endTime < $startTime){
				$result->addError('Invalid Time Range: You must select an End Time after Start Time');
				return $result;
			}
		}		
		
		if($this->EndDate){
			$startDate = date($this->StartDate);
			$endDate = date($this->EndDate);
			if($endDate < $startDate){
				$result->addError('Invalid Date Range: You must select an End Date after Start Date');
			}
		} 

		return $result;

	}

	private static $has_one = [
		'Event' => Event::class,
	];

	public function getTitle()
	{
		return $this->StartDate . ' ' . $this->StartTime;
	}
	
	public function forTemplate()
	{
		return $this->getTitle();
	}

	public function getCMSFields()
	{
		$fields = FieldList::create([
			DateField::create('StartDate',_t(__CLASS__ . '.STARTDATE', 'Start Date')),
			TimeField::create('StartTime',_t(__CLASS__ . '.STARTTIME', 'Start Time')),
			DateField::create('EndDate',_t(__CLASS__ . '.ENDDATE', 'End Date')),
			TimeField::create('EndTime',_t(__CLASS__ . '.STARTTIME', 'End Time')),
			CheckboxField::create('AllDay',_t(__CLASS__ . '.ALLDAY', 'All Day Event?')),	
		]
		);

		return $fields;
	}

	public function getCMSValidator()
	{
		return new RequiredFields([
			'StartDate',
			'StartTime'
		]);
	}	
}
