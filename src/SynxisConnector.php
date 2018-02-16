<?php

namespace GurwinderAntal\crs;

use GurwinderAntal\crs\DataType\HotelAvailRQ\AvailRequestSegment;
use GurwinderAntal\crs\DataType\HotelAvailRQ\Criterion;
use GurwinderAntal\crs\DataType\HotelAvailRQ\GuestCount;
use GurwinderAntal\crs\DataType\HotelAvailRQ\HotelAvailRQ;
use GurwinderAntal\crs\DataType\HotelAvailRQ\RoomStayCandidate;
use GurwinderAntal\crs\DataType\HotelAvailRQ\StayDateRange;
use GurwinderAntal\crs\DataType\shared\POS;

/**
 * Class SynxisConnector
 *
 * @package GurwinderAntal\crs
 */
class SynxisConnector {

    /**
     * @var \SoapClient
     */
    protected $client;

    /**
     * SynxisConnector constructor.
     *
     * @param $wsdl
     *    URI of the WSDL file.
     * @param array $options
     *    An array of options.
     *
     * @throws \Exception
     */
    public function __construct($wsdl, $options = []) {
        if (!class_exists('SoapClient')) {
            throw new \Exception('PHP SOAP extension not installed.');
        }
        $this->client = new \SoapClient($wsdl, $options);
        $this->setHeaders('Elevated Third', '***REMOVED***', '***REMOVED***');
    }

    /**
     * Wrapper to get a list of available SOAP functions.
     *
     * @return array
     *    An array containing SOAP function prototypes.
     */
    public function getFunctions() {
        return $this->client->__getFunctions();
    }

    /**
     * Set SOAP headers.
     *
     * @param $systemId
     * @param $username
     * @param $password
     */
    public function setHeaders($systemId, $username, $password) {
        $namespace = 'http://htng.org/1.1/Header/';
        $uNode = new \SoapVar($username, XSD_STRING, NULL, NULL, 'userName', $namespace);
        $pNode = new \SoapVar($password, XSD_STRING, NULL, NULL, 'password', $namespace);
        $credential = new \SoapVar([
            $uNode,
            $pNode,
        ], SOAP_ENC_OBJECT, NULL, NULL, 'Credential', $namespace);
        $from = new \SoapVar([$credential], SOAP_ENC_OBJECT, NULL, NULL, 'From', $namespace);
        $headerBody = new \SoapVar([$from], SOAP_ENC_OBJECT, NULL, NULL, 'HTNGHeader', $namespace);
        $header = new \SoapHeader($namespace, 'HTNGHeader', $headerBody, FALSE);
        $this->client->__setSoapHeaders($header);
    }

    /**
     * Checks availability.
     *
     * @param array $params
     *    An array with the following keys:
     *       - channelCode
     *       - channelId,
     *       - ageQualifyingCode
     *       - guestCount
     *       - quantity
     *       - hotelCode
     *       - startDate
     *       - endDate
     *       - langCode
     *
     * @return mixed
     */
    public function checkAvailability($params) {
        // Build POS
        $pos = new POS($params['channelCode'], $params['channelId']);
        // Build GuestCount
        $guestCounts = [
            new GuestCount($params['ageQualifyingCode'], $params['guestCount']),
        ];
        // Build RoomStayCandidates
        $roomStayCandidates = [
            new RoomStayCandidate($params['quantity'], $guestCounts),
        ];
        // Build Criteria
        $criteria = [
            new Criterion($params['hotelCode']),
        ];
        // Build StayDateRange
        $stayDateRange = new StayDateRange($params['startDate'], $params['endDate']);
        // Build AvailRequestSegments
        $availRequestSegments = [
            new AvailRequestSegment('Room', $stayDateRange, $roomStayCandidates, $criteria),
        ];
        // Build request
        $hotelAvailRQ = new HotelAvailRQ(10, $params['langCode'], FALSE, $pos, $availRequestSegments);
        $request = [
            'OTA_HotelAvailRQ' => $hotelAvailRQ->getRequestData(),
        ];
        // Send request
        $response = $this->client->__soapCall('CheckAvailability', $request);
        return $response;
    }

}
