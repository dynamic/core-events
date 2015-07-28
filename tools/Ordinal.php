<?php

/**
 * Class Ordinal
 * @package uw-union
 */
class Ordinal {

    /**
     * This function determines the
     * ordinal suffix of any given number
     * @link http://stackoverflow.com/a/3110033/995883
     *
     * @param $number
     * @return string
     */
    public static function getOrdinalSuffix($number){
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return $number. 'th';
        else
            return $number. $ends[$number % 10];
    }

	/**
	 * Function that returns a textual
	 * ordinal of a given number
	 * @link http://webdeveloperblog.tiredmachine.com/php-converting-an-integer-123-to-ordinal-word-firstsecondthird/
	 *
	 * @param $num
	 * @return mixed
	 */
	public static function getNumToOrdinalWord($num){
		$first_word = array('eth','First','Second','Third','Fourth','Fifth','Sixth','Seventh','Eighth','Ninth','Tenth','Eleventh','Twelfth','Thirteenth','Fourteenth','Fifteenth','Sixteenth','Seventeenth','Eighteenth','Nineteenth','Twentieth');
		$second_word =array('','','Twenty','Thirty','Forty','Fifty');

		if($num <= 20)
			return $first_word[$num];

		$first_num = substr($num,-1,1);
		$second_num = substr($num,-2,1);

		return $string = str_replace('y-eth','ieth',$second_word[$second_num].'-'.$first_word[$first_num]);
	}

}