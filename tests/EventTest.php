<?php

class EventPageTest extends DC_Test{

    protected static $use_draft_site = true;

    function setUp(){
        parent::setUp();

        $holder = EventHolder::create();
        $holder->Title = "Events";
        $holder->doPublish();
    }

    function testEventCreation(){

        $this->logInWithPermission('CREATE_EVENT');
        $page = singleton('EventPage');
        $this->assertTrue($page->canCreate());

        $event = new Event();
        $event->Title = 'Our First Event';
        $event->StartDate = date('Y-m-d', strtotime('Next Thursday'));
        $event->StartTime = date('H:i:s', strtotime('5:30 pm'));
        $event->write();
        $eventID = $event->ID;

        $this->assertTrue($eventID == Event::get()->first()->ID);

        $this->logOut();

    }

    function testEventDeletion(){

        $this->logInWithPermission('ADMIN');

        $event = new Event();
        $event->Title = 'Our First Event';
        $event->StartDate = date('Y-m-d', strtotime('Next Thursday'));
        $event->StartTime = date('H:i:s', strtotime('5:30 pm'));
        $event->write();
        $eventID = $event->ID;

        $this->logOut();
        $this->logInWithPermission('DELETE_EVENT');
        $this->assertTrue($event->canDelete());

        $event->delete();
        $this->assertTrue(!Event::get()->byID($eventID));

    }

}
