<?php

namespace BluehouseGroup\Event;

use SilverStripe\Core\Extensible;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextAreaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Dev\Debug;

class Event extends DataObject
{
    private static $extensions = [
        Versioned::class
    ];

    private static $table_name = 'Event';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'HTMLText',
        'URLSegment' => 'Varchar(63)'
    ];

    private static $has_many = [
        'EventDateTimes' => EventDateTime::class,
    ];

    private static $has_one = [
        'Calendar' => CalendarPage::class,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'StartTimeSummary' => 'Event Date/Time',
        'URLSegment' => 'URLSegment'
    ];

    private static $searchable_fields = [
        'Title',
        'Description'
    ];

    private static $default_sort = 'Title DESC';

    public function getStartTimeSummary()
    {
        $date = $this->EventDateTimes()->sort([
            'StartDate' => 'desc',
            'StartTime' => 'desc'
        ])->first();

        return $date;
    }

    public function getDateTime($date, $time) {
        if ($time == NULL) {
            return $this->EventDateTimes()->filter([
                'StartDate' => date('Y-m-d',strtotime($date)),
                'AllDay' => true])->first();
        }

        $dateTimes = $this->EventDateTimes();
        $dateTime = $dateTimes->filter([
            'StartDate' => date('Y-m-d',strtotime($date)),
            'StartTime' => $time])->first();

        return $dateTime;
    }

    public function getResources()
    {
        return Resource::getByResourceType($this->SharePointTag);
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // If there is no URLSegment set, generate one from Title
        $isDefaultSegment = stripos($this->owner->URLSegment, $this->owner->singular_name()) === 0;
        if ((!$this->owner->URLSegment || $isDefaultSegment) && $this->URLSegmentTitle()) {
            $this->owner->URLSegment = $this->generateURLSegment($this->URLSegmentTitle());
        } elseif ($this->owner->isChanged('URLSegment', 2)) {
            // Do a strict check on change level, to avoid double encoding caused by
            // bogus changes through forceChange()
            $filter = URLSegmentFilter::create();
            $this->owner->URLSegment = $filter->filter($this->owner->URLSegment);
            // If after sanitising there is no URLSegment, give it a reasonable default
            if (!$this->owner->URLSegment) {
                $this->owner->URLSegment = $this->owner->singular_name() . "-" . $this->owner->ID;
            }
        }

        // Ensure that this object has a non-conflicting URLSegment value.
        $count = 2;
        while (!$this->validURLSegment()) {
            $this->owner->URLSegment = preg_replace('/-[0-9]+$/', null, $this->owner->URLSegment) . '-' . $count;
            $count++;
        }

    }

    public function onAfterDelete()
    {
        parent::onAfterDelete();

        // Also delete related EventDateTime records to prevent them from being orphaned
        foreach ($this->EventDateTimes() as $eventdatetime) {
            $eventdatetime->delete();
        }
    }

    public function generateURLSegment($title)
    {
        $filter = URLSegmentFilter::create();
        $filteredTitle = $filter->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (!$filteredTitle || $filteredTitle == '-' || $filteredTitle == '-1') {
            $filteredTitle = $this->owner->singular_name() . "-" . $this->owner->ID;
        }

        // Hook for extensions
        $this->extend('updateURLSegment', $filteredTitle, $title);

        return $filteredTitle;
    }

    public function validURLSegment()
    {
        // Check for clashing pages by url, id, and parent
        $class_name = $this->owner->getClassName();
        $source = $class_name::get()->filter([
            'URLSegment' => $this->owner->URLSegment
        ]);
        if ($this->owner->ID) {
            $source = $source->exclude('ID', $this->owner->ID);
        }

        return !$source->exists();
    }

    // attribute to use for url segments
    // defaults to Title but may be overridden in the specific classes
    public function URLSegmentTitle()
    {
        $title = $this->owner->Title;

        $this->extend('updateURLSegmentTitle', $title);

        return $title;
    }

    /**
     * Return the link for this object, with the {@link Director::baseURL()} included.
     *
     * @param string $action optional additional url parameters
     * @return string
     */
    public function Link($action = null)
    {
        $calendar = ($this->CalendarID ? CalendarPage::get()->filter(['ID' => $this->CalendarID])->first() : CalendarPage::get()->sort(['Created' => 'ASC'])->first() );
        $link = Controller::join_links(Director::baseURL(), $calendar->Link('event'), '/' . $this->URLSegment);
        return $link;
    }

    public function BaseLink()
    {
        $calendar = ($this->CalendarID ? CalendarPage::get()->filter(['ID' => $this->CalendarID])->first() : CalendarPage::get()->sort(['Created' => 'ASC'])->first() );
        $link = Director::absoluteURL(Controller::join_links(Director::baseURL(), $calendar->Link('event'), '/'));
        return $link;
    }

    public function getCMSFields()
    {
        $fields = FieldList::create(
            TabSet::create('Root',
                Tab::create('Main',
                    TextField::create('Title',_t(__CLASS__ . '.TITLE', 'Title')),
                    SiteTreeURLSegmentField::create(
                        'URLSegment',
                        'URL Segment'
                    )->setURLPrefix($this->BaseLink()),
                    HTMLEditorField::create('Description',_t(__CLASS__ . '.DESCRIPTION', 'Description')),
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

        $event_date_times_config->removeComponentsByType(
            GridFieldAddExistingAutocompleter::class
        );

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }
}
