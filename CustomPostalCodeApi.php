<?php

/**
 * REST EndPoint for Fetching Location and Country on basis of Postal Code
 */
class CustomPostalCodeApi extends SugarApi
{

    public function registerApiRest()
    {
        return array(
            'getLocationfromZipCode' => array(
                'reqType' => 'POST',
                'path' => array('getLocationfromZipCode'),
                'pathVars' => array('', ''),
                'method' => 'getLocationfromZipCode',
            )
        );
    }
 
    /**
     * CCRED-1033 : ZIP-Key: Validation
     * 
     * @param  type $api
     * @param  type $args
     * @return Array
     */
    public function getLocationfromZipCode($api, $args)
    {
        $this->requireArgs($args, array('zipcode'));
        $locations = array();
        $country = '';
        $query = "SELECT * FROM zipcodes WHERE postal_code LIKE '" . $args["zipcode"] . "' AND deleted = 0";
        $result = $GLOBALS['db']->query($query);

        if ($result->num_rows > 0) {
            while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
                $locations[] = $row['location'];
                $country = $row['country'];
            }
        }

        return array('cities' => $locations, 'country' => $country);
    }
}
