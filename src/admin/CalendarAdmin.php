<?php

namespace BluehouseGroup\Event;

use SilverStripe\Admin\ModelAdmin;

class CalendarAdmin extends ModelAdmin
{
    private static $managed_models = [
        CalendarPage::class,
        Event::class,
        EventDateTime::class
    ];

    private static $menu_icon_class = 'font-icon-calendar'; 
    private static $url_segment = 'calendars';
    private static $menu_title = 'Calendars';
}
