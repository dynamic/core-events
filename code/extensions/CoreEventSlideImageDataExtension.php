<?php

class CoreEventSlideImageDataExtension extends DataExtension
{

    private static $db = array();
    private static $has_one = array(
        'Event' => 'Event'
    );
    private static $has_many = array();
    private static $many_many = array();
    private static $many_many_extraFields = array();
    private static $belongs_many_many = array();

    public function updateCMSFields(FieldList $fields)
    {


    }

}