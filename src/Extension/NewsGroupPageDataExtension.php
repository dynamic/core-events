<?php

namespace Dynamic\CoreEvents\Extension;


use Dynamic\CoreEvents\Page\EventHolder;
use SilverStripe\ORM\DataExtension;



class NewsGroupPageDataExtension extends DataExtension
{

    private static $allowed_children = array(
        EventHolder::class
    );

}