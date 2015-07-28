<?php

class EventModelAdmin extends ModelAdmin{

    private static $managed_models = array(
        'Event'
    );
    private static $model_importers = array();
    private static $url_segment = 'events';
    private static $menu_title = 'Events';

}