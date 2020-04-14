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
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;

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
        'AllDay' => 'Boolean',
        'Description' => 'HTMLText',
    ];

    private static $defaults = [
        'StartDate' => '',
        'StartTime' => '',
        'EndDate' => '',
        'EndTime' => ''
    ];

    public function populateDefaults()
    {
        $today = date('Y-m-d');
        $this->StartDate = $today;
        $this->EndDate = $today;
        $this->StartTime = '3:00 PM';
        $this->EndTime = '4:00 PM';
        parent::populateDefaults();
    }

    private static $has_one = [
        'Event' => Event::class,
    ];

    public function validate()
    {

        $result = parent::validate();

        // if ($this->StartTime && $this->EndTime) {
        //     $startTime = date("$this->StartDate $this->StartTime");
        //     $endTime = ($this->EndDate ? "$this->EndDate $this->EndTime" : "$this->StartDate $this->EndTime");
        //     if ($endTime < $startTime) {
        //         $result->addError('Invalid Time Range: You must select an End Time after Start Time');
        //         return $result;
        //     }
        // }
        //
        // if ($this->EndDate) {
        //     $startDate = date($this->StartDate);
        //     $endDate = date($this->EndDate);
        //     if ($endDate < $startDate) {
        //         $result->addError('Invalid Date Range: You must select an End Date after Start Date');
        //     }
        // }

        $filter = [
            'EventID' => $this->Event->ID,
            'StartDate' => $this->StartDate
        ];

        if ($this->StartTime) {
            $filter['StartTime'] = $this->StartTime;
        } else {
            $filter['AllDay'] = 1;
        }
        $existingDT = EventDateTime::get()->filter($filter)->toArray();
        if (sizeof($existingDT) > 1) {
            $result->addError("Conflict with existing Occurrence, please choose a start time different than $this->StartTime");
        }

        return $result;

    }

    public function getTitle()
    {
        return $this->StartDate . ' ' . $this->StartTime;
    }

    public function forTemplate()
    {
        return $this->getTitle();
    }

    public function Link($action = null)
    {

        $controller = CalendarPage::get()->first();

        $urlsegment_with_date = $this->Event->URLSegment . '/' . $this->StartDate;

        if ($this->AllDay == 0) {
            $date_string = strtotime($this->StartDate . ' ' . $this->StartTime);
            $urlsegment_with_date .= '/' . date('Hi', $date_string);
        }

        $link = Controller::join_links(Director::baseURL(), $controller->Link('event'), '/' . $urlsegment_with_date);

        return $link;
    }

    public function AbsoluteLink($action = null)
    {
        return Director::absoluteURL($this->Link($action));
    }

    public function getCMSFields()
    {

        $fields = FieldList::create(
            TabSet::create('Root',
                Tab::create('Main',
                    CheckboxField::create('AllDay', _t(__CLASS__ . '.ALLDAY', 'All Day Event?')),
                    DateField::create('StartDate', _t(__CLASS__ . '.STARTDATE', 'Start Date')),
                    TimeField::create('StartTime', _t(__CLASS__ . '.STARTTIME', 'Start Time')),
                    DateField::create('EndDate', _t(__CLASS__ . '.ENDDATE', 'End Date')),
                    TimeField::create('EndTime', _t(__CLASS__ . '.STARTTIME', 'End Time')),
                    HTMLEditorField::create('Description', _t(__CLASS__ . '.DESCRIPTION', 'Description'))
                ))
        );

        $this->extend('updateCMSFields', $fields);

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
