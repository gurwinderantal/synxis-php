<?php

namespace GurwinderAntal\crs;

use GurwinderAntal\crs\Type\Common\AddressInfo;
use GurwinderAntal\crs\Type\Common\CountryName;
use GurwinderAntal\crs\Type\Common\Customer;
use GurwinderAntal\crs\Type\Common\DateTimeSpan;
use GurwinderAntal\crs\Type\Common\Guarantee;
use GurwinderAntal\crs\Type\Common\GuaranteeAccepted;
use GurwinderAntal\crs\Type\Common\GuestCount;
use GurwinderAntal\crs\Type\Common\GuestCounts;
use GurwinderAntal\crs\Type\Common\HotelReferenceGroup;
use GurwinderAntal\crs\Type\Common\HotelSearchCriterion;
use GurwinderAntal\crs\Type\Common\Membership;
use GurwinderAntal\crs\Type\Common\PersonName;
use GurwinderAntal\crs\Type\Common\Policies;
use GurwinderAntal\crs\Type\Common\Profile;
use GurwinderAntal\crs\Type\Common\RatePlan;
use GurwinderAntal\crs\Type\Common\RoomStay;
use GurwinderAntal\crs\Type\Common\RoomType;
use GurwinderAntal\crs\Type\Common\StateProv;
use GurwinderAntal\crs\Type\Common\Telephone;
use GurwinderAntal\crs\Type\Common\TPA_Extensions;
use GurwinderAntal\crs\Type\Request\AvailRequestSegment;
use GurwinderAntal\crs\Type\Request\Comment;
use GurwinderAntal\crs\Type\Request\CompanyName;
use GurwinderAntal\crs\Type\Request\DateTimeSpanType;
use GurwinderAntal\crs\Type\Request\HotelDescriptiveInfo;
use GurwinderAntal\crs\Type\Request\HotelReservation;
use GurwinderAntal\crs\Type\Request\HotelResModify;
use GurwinderAntal\crs\Type\Request\OTA_CancelRQ;
use GurwinderAntal\crs\Type\Request\OTA_HotelAvailRQ;
use GurwinderAntal\crs\Type\Request\OTA_HotelDescriptiveInfoRQ;
use GurwinderAntal\crs\Type\Request\OTA_HotelResModifyRQ;
use GurwinderAntal\crs\Type\Request\OTA_HotelResRQ;
use GurwinderAntal\crs\Type\Request\OTA_ReadRQ;
use GurwinderAntal\crs\Type\Request\PaymentCard;
use GurwinderAntal\crs\Type\Request\POS;
use GurwinderAntal\crs\Type\Request\ProfileInfo;
use GurwinderAntal\crs\Type\Request\RatePlanCandidate;
use GurwinderAntal\crs\Type\Request\ReadRequest;
use GurwinderAntal\crs\Type\Request\ReadRequests;
use GurwinderAntal\crs\Type\Request\RequestorID;
use GurwinderAntal\crs\Type\Request\ResGlobalInfo;
use GurwinderAntal\crs\Type\Request\ResGuest;
use GurwinderAntal\crs\Type\Request\RoomStayCandidate;
use GurwinderAntal\crs\Type\Request\Source;
use GurwinderAntal\crs\Type\Request\SpecialRequest;
use GurwinderAntal\crs\Type\Request\SupplementalData;
use GurwinderAntal\crs\Type\Request\UniqueID;
use GurwinderAntal\crs\Type\Request\Verification;
use GurwinderAntal\crs\Type\Request\WrittenConfInst;
use GurwinderAntal\crs\Type\Response\ResCommonDetailType;
use GurwinderAntal\crs\Type\Response\Service;

/**
 * Class SynxisConnector
 * Provides functionality specific to SynXis.
 *
 * @package GurwinderAntal\crs
 */
class SynxisConnector extends CrsConnectorBase {

    /**
     * Timestamp format.
     */
    const TIMESTAMP_ZONE = 'Europe/London';

    const TIMESTAMP_FORMAT = "Y-m-d\TH:i:s+00:00";

    /**
     * {@inheritdoc}
     */
    public function checkAvailability($params) {
        // Instantiate SOAP client
        $this->initializeClient('http://htng.org/1.1/Header/', [
            'OTA_HotelAvailRQ' => 'GurwinderAntal\crs\Type\Request\OTA_HotelAvailRQ',
            'OTA_HotelAvailRS' => 'GurwinderAntal\crs\Type\Response\OTA_HotelAvailRS',
        ]);

        // Build POS->Source->RequestorID->CompanyName
        $companyName = new CompanyName(
            $params['CodeContext'] ?? NULL,
            $params['CompanyShortName'] ?? NULL,
            $params['TravelSelector'] ?? NULL,
            $params['POS']['Code'] ?? NULL
        );
        // Build POS->Source->RequestorID
        $requestorId = new RequestorID(
            $companyName,
            NULL,
            $params['POS']['ID'] ?? NULL,
            $params['POS']['ID_Context'] ?? NULL,
            $params['Instance'] ?? NULL,
            $params['PinNumber'] ?? NULL,
            $params['MessagePassword'] ?? NULL
        );
        // Build POS->Source
        $source = new Source(
            NULL,
            $requestorId
        );
        // Build OTA_HotelAvailRQ->POS
        $pos = new POS($source);

        // Build AvailRequestSegment->StayDateRange
        $stayDateRange = new DateTimeSpan(
            $params['Start'] ?? NULL,
            $params['End'] ?? NULL,
            $params['Duration'] ?? NULL,
            NULL
        );
        // Build AvailRequestSegment->RatePlanCandidates
        $ratePlanCandidates = array_key_exists('PromotionCode', $params) ||
        array_key_exists('RatePlanCode', $params) ? [
            new RatePlanCandidate(
                NULL,
                NULL,
                $params['PromotionCode'] ?? NULL,
                $params['RatePlanCode'] ?? NULL,
                $params['RatePlanType'] ?? NULL,
                $params['RatePlanId'] ?? NULL,
                NULL,
                $params['RatePlanQualifier'] ?? NULL,
                $params['RatePlanCategory'] ?? NULL,
                $params['RatePlanFilterCode'] ?? NULL
            ),
        ] : NULL;
        // Build AvailRequestSegment->RoomStayCandidate->GuestCounts
        $guestCounts = [];
        foreach ($params['Count'] as $aqc => $count) {
            $aqc = 'self::AQC_' . strtoupper($aqc);
            $guestCounts[] = new GuestCount(constant($aqc), $count, NULL);
        }
        // Build AvailRequestSegment->RoomStayCandidates
        $roomStayCandidates = [
            new RoomStayCandidate(
                $guestCounts,
                $params['Quantity'] ?? NULL,
                $params['RoomType'] ?? NULL,
                $params['RoomTypeCode'] ?? NULL,
                $params['RoomCategory'] ?? NULL,
                NULL,
                $params['NonSmoking'] ?? NULL,
                NULL,
                NULL
            ),
        ];
        // Build AvailRequestSegment->HotelSearchCriteria
        $hotelSearchCriteria = [];
        foreach ((array) $params['HotelCode'] as $hotelCode) {
            // Build AvailRequestSegment->HotelSearchCriteria->Criterion->HotelRef
            $hotelRef = new HotelReferenceGroup(
                $hotelCode,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            );
            // Build AvailRequestSegment->HotelSearchCriteria->Criterion
            $hotelSearchCriteria[] = new HotelSearchCriterion(
                NULL,
                NULL,
                $hotelRef,
                NULL,
                NULL,
                NULL,
                NULL
            );
        }
        // Build AvailRequestSegments
        $availRequestSegments = [
            new AvailRequestSegment(
                $stayDateRange,
                NULL,
                $ratePlanCandidates,
                NULL,
                $roomStayCandidates,
                $hotelSearchCriteria,
                NULL,
                $params['ResponseType'] ?? NULL,
                $params['AvailReqType'] ?? NULL,
                NULL
            ),
        ];

        // Build OTA_HotelAvailRQ
        $request = new OTA_HotelAvailRQ(
            $params['EchoToken'] ?? NULL,
            $params['PrimaryLangID'] ?? NULL,
            $params['AltLangID'] ?? NULL,
            $this->timestamp(),
            $params['Target'] ?? NULL,
            $params['Version'] ?? NULL,
            $params['MessageContentCode'] ?? NULL,
            NULL,
            $pos,
            $availRequestSegments,
            NULL,
            $params['MaxResponses'] ?? NULL,
            $params['RequestedCurrency'] ?? NULL,
            $params['ExactMatchOnly'] ?? FALSE,
            $params['BestOnly'] ?? FALSE,
            $params['SummaryOnly'] ?? FALSE,
            $params['HotelStayOnly'] ?? FALSE,
            $params['PricingMethod'] ?? NULL,
            $params['AvailRatesOnly'] ?? FALSE,
            $params['SequenceNmbr'] ?? NULL
        );

        try {
            $response = $this->client->CheckAvailability($request);
            if ($this->debug) {
                $this->logMessage(__FUNCTION__);
            }
            return $response;
        } catch (\Exception $exception) {
            // Handle error.
            return NULL;
        }
    }

    /**
     * Returns formatted timestamp.
     *
     * @return false|string
     */
    public function timestamp() {
        date_default_timezone_set(self::TIMESTAMP_ZONE);
        return date(self::TIMESTAMP_FORMAT);
    }

    /**
     * {@inheritdoc}
     */
    public function createReservation($params, $config) {
        // Instantiate SOAP client
        $this->initializeClient('http://htng.org/1.1/Header/', [
            'OTA_HotelResRQ' => 'GurwinderAntal\crs\Type\Request\OTA_HotelResRQ',
            'OTA_HotelResRS' => 'GurwinderAntal\crs\Type\Response\OTA_HotelResRS',
        ], TRUE, $config);

        // Build POS->Source->RequestorID->CompanyName
        $companyName = new CompanyName(
            $params['CodeContext'] ?? NULL,
            $params['CompanyShortName'] ?? NULL,
            $params['TravelSelector'] ?? NULL,
            $params['POS']['Code'] ?? NULL
        );
        // Build POS->Source->RequestorID
        $requestorId = new RequestorID(
            $companyName,
            NULL,
            $params['POS']['ID'] ?? NULL,
            $params['POS']['ID_Context'] ?? NULL,
            $params['Instance'] ?? NULL,
            $params['PinNumber'] ?? NULL,
            $params['MessagePassword'] ?? NULL
        );
        // Build POS->Source
        $source = new Source(
            NULL,
            $requestorId
        );
        // Build OTA_HotelResRQ->POS
        $pos = new POS($source);

        // Build HotelReservation->RoomStay->RoomTypes
        $roomTypes = [
            new RoomType(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $params['IsRoom'] ?? NULL,
                $params['RoomTypeCode'] ?? NULL,
                $params['InvBlockCode'] ?? NULL,
                $params['NumberOfUnits'] ?? NULL,
                NULL
            ),
        ];
        // Build HotelReservation->RoomStay->RatePlans
        $ratePlans = [
            new RatePlan(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $params['MealsIncluded'] ?? NULL,
                $params['RatePlanCode'] ?? NULL,
                $params['RatePlanName'] ?? NULL,
                $params['AccrualIndicator'] ?? NULL,
                $params['AutoEnrollmentIndicator'] ?? NULL,
                $params['BookingCode'] ?? NULL,
                $params['RatePlanType'] ?? NULL,
                $params['RatePlanID'] ?? NULL,
                $params['EffectiveDate'] ?? NULL,
                $params['ExpireDate'] ?? NULL,
                $params['CurrencyCode'] ?? NULL,
                $params['TaxInclusive'] ?? NULL,
                $params['PrepaidIndicator'] ?? NULL,
                $params['RatePlanCategory'] ?? NULL,
                $params['AvailabilityStatus'] ?? NULL,
                $params['PriceViewableInd'] ?? NULL
            ),
        ];
        // Build HotelReservation->RoomStay->GuestCounts->GuestCount
        $guestCount = [];
        foreach ($params['Count'] as $aqc => $count) {
            $aqc = 'self::AQC_' . strtoupper($aqc);
            $guestCount[] = new GuestCount(constant($aqc), $count, NULL);
        }
        // Build HotelReservation->RoomStay->GuestCounts
        $guestCounts = new GuestCounts(
            $guestCount,
            $params['IsPerRoom'] ?? NULL
        );
        // Build HotelReservation->RoomStay->TimeSpan
        $timeSpan = new DateTimeSpan(
            $params['Start'] ?? NULL,
            $params['End'] ?? NULL,
            $params['Duration'] ?? NULL,
            NULL
        );
        // Build HotelReservation->RoomStay->BasicPropertyInfo
        $basicPropertyInfo = new HotelReferenceGroup(
            $params['HotelCode'] ?? NULL,
            $params['HotelName'] ?? NULL,
            $params['AreaID'] ?? NULL,
            $params['HotelCodeContext'] ?? NULL,
            $params['ChainCode'] ?? NULL,
            $params['ChainName'] ?? NULL,
            $params['BrandCode'] ?? NULL,
            $params['BrandName'] ?? NULL,
            $params['HotelCityCode'] ?? NULL
        );
        // Build HotelReservation->RoomStay->SpecialRequests
        if (array_key_exists('SpecialRequests', $params)) {
            $specialRequests = [];
            foreach ($params['SpecialRequests'] as $specialRequest) {
                $specialRequests[] = new SpecialRequest(
                    $specialRequest['Text'] ?? NULL,
                    $specialRequest['Name'] ?? NULL,
                    $specialRequest['RequestCode'] ?? NULL,
                    $specialRequest['Description'] ?? NULL
                );
            }
        }
        else {
            $specialRequests = NULL;
        }
        if (array_key_exists('MembershipID', $params['ResGuests'][0]) && !empty($params['ResGuests'][0]['MembershipID'])) {
            $memberships = [
                new Membership(
                    $params['ProgramID'] ?? NULL,
                    $params['BonusCode'] ?? NULL,
                    $params['ResGuests'][0]['AccountID'] ?? NULL,
                    $params['ResGuests'][0]['MembershipID'] ?? NULL,
                    $params['TravelSector'] ?? NULL,
                    $params['PointsEarned'] ?? NULL
                ),
            ];
        }
        else {
            $memberships = NULL;
        }
        // Build HotelReservation->RoomStays
        $roomStays = [
            new RoomStay(
                NULL,
                NULL,
                NULL,
                NULL,
                $roomTypes,
                $ratePlans,
                NULL,
                $guestCounts,
                $timeSpan,
                $specialRequests,
                $basicPropertyInfo,
                NULL,
                NULL,
                NULL,
                NULL,
                $memberships,
                $params['MarketCode'] ?? NULL,
                $params['SourceOfBusiness'] ?? NULL,
                $params['IndexNumber'] ?? NULL
            ),
        ];
        $resGuests = [];
        foreach ($params['ResGuests'] as $resGuest) {
            // Build HotelReservation->ResGuest->Profiles->Profile->Customer
            $customer = new Customer(
                new PersonName(
                    $resGuest['NamePrefix'] ?? NULL,
                    $resGuest['NameTitle'] ?? NULL,
                    $resGuest['GivenName'] ?? NULL,
                    $resGuest['MiddleName'] ?? NULL,
                    $resGuest['Surname'] ?? NULL,
                    $resGuest['NameSuffix'] ?? NULL,
                    $resGuest['NameType'] ?? NULL
                ),
                new Telephone(
                    $resGuest['FormattedInd'] ?? FALSE,
                    $resGuest['PhoneTechType'] ?? NULL,
                    $resGuest['PhoneNumber'] ?? NULL,
                    $resGuest['PhoneUseType'] ?? NULL,
                    $resGuest['DefaultInd'] ?? FALSE
                ),
                $resGuest['Email'] ?? NULL,
                new AddressInfo(
                    $resGuest['AddressLine'] ?? NULL,
                    $resGuest['CityName'] ?? NULL,
                    $resGuest['PostalCode'] ?? NULL,
                    new StateProv($resGuest['StateCode'] ?? NULL),
                    new CountryName($resGuest['Code'] ?? NULL),
                    $resGuest['Type'] ?? NULL,
                    $resGuest['Remark'] ?? NULL,
                    $resGuest['CompanyName'] ?? NULL,
                    $resGuest['FormattedInd'] ?? FALSE,
                    $resGuest['DefaultInd'] ?? FALSE
                ),
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $resGuest['BirthDate'] ?? NULL,
                $resGuest['Gender'] ?? NULL,
                $resGuest['CustomerValue'] ?? NULL,
                $resGuest['LockoutType'] ?? NULL,
                $resGuest['Language'] ?? NULL
            );
            // Build HotelReservation->ResGuest->Profiles->Profile
            $profile = new Profile(
                NULL,
                NULL,
                $customer,
                NULL,
                NULL,
                NULL,
                NULL,
                $resGuest['ProfileType'] ?? NULL,
                NULL,
                NULL,
                NULL,
                $resGuest['ShareAllMarketInd'] ?? NULL
            );
            // Build HotelReservation->ResGuest->Profiles
            $profiles = [
                new ProfileInfo(
                    NULL,
                    $profile,
                    NULL
                ),
            ];
            // Build HotelReservation->ResGuests
            $resGuests[] = new ResGuest(
                NULL,
                $profiles,
                NULL,
                NULL,
                $params['PrimaryIndicator'] ?? NULL,
                $params['RPH'] ?? NULL,
                NULL
            );
        }
        // Add any comments
        if (array_key_exists('Comments', $params)) {
            $comments = [];
            foreach ($params['Comments'] as $comment) {
                $comments[] = new Comment($comment['Text']);
            }
        }
        else {
            $comments = NULL;
        }
        if ($this->array_keys_exist([
            'CardCode',
            'CardNumber',
            'CardExpireDate',
        ], $params)) {
            // Build HotelReservations->ResGlobalInfo->Guarantee->GuaranteesAccepted
            $guaranteesAccepted = [
                new GuaranteeAccepted(
                    new PaymentCard(
                        $params['CardHolderName'] ?? NULL,
                        NULL,
                        NULL,
                        $params['CardType'] ?? NULL,
                        $params['CardCode'] ?? NULL,
                        $params['CardNumber'] ?? NULL,
                        $params['SeriesCode'] ?? NULL,
                        $params['CardExpireDate'] ?? NULL
                    ),
                    NULL,
                    NULL
                ),
            ];
            // Build HotelReservations->ResGlobalInfo->Guarantee
            $guarantee = new Guarantee(
                $guaranteesAccepted,
                NULL,
                NULL,
                NULL,
                NULL
            );
        }
        else {
            $guarantee = NULL;
        }
        if ($comments != NULL || $guarantee != NULL) {
            // Build HotelReservations->ResGlobalInfo
            $resGlobalInfo = new ResGlobalInfo(
                $comments,
                $guarantee,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            );
        }
        else {
            $resGlobalInfo = NULL;
        }
        // Build OTA_HotelResRQ->HotelReservations->WrittenConfInst
        $writtenConfInst = array_key_exists('EmailTemplate', $params) ?
            new WrittenConfInst(
                new SupplementalData(
                    NULL,
                    $params['EmailTemplate'],
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL),
                NULL,
                NULL,
                NULL,
                NULL
            ) : NULL;
        // Build OTA_HotelResRQ->HotelReservations->Services
        if (array_key_exists('Services', $params)) {
            $services = [];
            foreach ($params['Services'] as $service) {
                // @TODO: Safe to derive GuestCounts from Quantity?
                // @TODO: TimeSpan needs to be flexible
                $serviceDetails = new ResCommonDetailType(
                    new GuestCounts(
                        [
                            new GuestCount(
                                self::AQC_ADULT,
                                $service['Quantity'] ?? NULL,
                                NULL
                            ),
                        ],
                        $params['IsPerRoom'] ?? NULL
                    ),
                    NULL,
                    new DateTimeSpanType(
                        $params['Start'] ?? NULL,
                        $params['End'] ?? NULL,
                        NULL,
                        NULL
                    ),
                    NULL,
                    NULL
                );
                $services[] = new Service(
                    $serviceDetails,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    $service['Quantity'] ?? NULL,
                    $service['Inclusive'] ?? NULL,
                    $service['ServiceInventoryCode'] ?? NULL,
                    $service['ServicePricingType'] ?? NULL,
                    $service['ServiceRPH'] ?? NULL
                );
            }
        }
        else {
            $services = NULL;
        }
        // Build OTA_HotelResRQ->HotelReservations
        $hotelReservations = [
            new HotelReservation(
                NULL,
                $roomStays,
                $resGuests,
                $resGlobalInfo,
                NULL,
                $writtenConfInst,
                $services,
                NULL,
                NULL,
                TRUE,
                $params['CreatorID'] ?? NULL,
                NULL,
                $params['LastModifierID'] ?? NULL,
                NULL
            ),
        ];

        // Build request
        $request = new OTA_HotelResRQ(
            $params['EchoToken'] ?? NULL,
            $params['PrimaryLangID'] ?? NULL,
            $params['AltLangID'] ?? NULL,
            $this->timestamp(),
            $params['Target'] ?? NULL,
            $params['Version'] ?? NULL,
            $params['MessageContentCode'] ?? NULL,
            NULL,
            $pos,
            $hotelReservations,
            NULL,
            'Commit',
            $params['RetransmissionIndicator'] ?? NULL
        );

        try {
            $response = $this->client->CreateReservations($request);
            if ($this->debug) {
                $this->logMessage(__FUNCTION__);
            }
            return $response;
        } catch (\Exception $exception) {
            // Handle error.
            return NULL;
        }
    }

    /**
     * @param array $keys
     *   Keys to check presence for.
     * @param array $array
     *   The array to check presence in.
     *
     * @return bool
     */
    public function array_keys_exist(array $keys, array $array) {
        return !array_diff_key(array_flip($keys), $array);
    }

    public function getReservation($params) {
        // Instantiate SOAP client
        $this->initializeClient('http://htng.org/1.1/Header/', [
            'OTA_ReadRQ'     => 'GurwinderAntal\crs\Type\Request\OTA_ReadRQ',
            'OTA_HotelResRS' => 'GurwinderAntal\crs\Type\Response\OTA_HotelResRS',
        ]);

        // Build POS->Source->RequestorID->CompanyName
        $companyName = new CompanyName(
            $params['CodeContext'] ?? NULL,
            $params['CompanyShortName'] ?? NULL,
            $params['TravelSelector'] ?? NULL,
            $params['POS']['Code'] ?? NULL
        );
        // Build POS->Source->RequestorID
        $requestorId = new RequestorID(
            $companyName,
            NULL,
            $params['POS']['ID'] ?? NULL,
            $params['POS']['ID_Context'] ?? NULL,
            $params['Instance'] ?? NULL,
            $params['PinNumber'] ?? NULL,
            $params['MessagePassword'] ?? NULL
        );
        // Build POS->Source
        $source = new Source(
            NULL,
            $requestorId
        );
        // Build OTA_ReadRQ->POS
        $pos = new POS($source);

        // Build ReadRequest->UniqueID
        $uniqueId = new UniqueID(
            NULL,
            $params['Type'] ?? self::UIT_RESERVATION,
            $params['ID'] ?? NULL,
            'CrsConfirmNumber',
            NULL,
            NULL
        );
        // Build ReadRequest->Verification
        $verification = new Verification(
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            new TPA_Extensions(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                new HotelReferenceGroup(
                    $params['HotelCode'] ?? NULL,
                    NULL,
                    NULL,
                    NULL,
                    $params['ChainCode'] ?? NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL
                ),
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            )
        );
        // Build OTA_ReadRQ->ReadRequests
        $readRequests = new ReadRequests(
            new ReadRequest(
                $uniqueId,
                $verification,
                NULL,
                NULL
            ),
            NULL,
            NULL,
            NULL
        );
        // Build request
        $request = new OTA_ReadRQ(
            $params['EchoToken'] ?? NULL,
            $params['PrimaryLangID'] ?? NULL,
            $params['AltLangID'] ?? NULL,
            $this->timestamp(),
            $params['Target'] ?? NULL,
            $params['Version'] ?? NULL,
            $params['MessageContentCode'] ?? NULL,
            NULL,
            $readRequests,
            NULL,
            $pos,
            $params['ReturnListIndicator'] ?? NULL,
            NULL,
            $params['MaxResponses'] ?? NULL
        );

        try {
            $response = $this->client->ReadReservations($request);
            if ($this->debug) {
                $this->logMessage(__FUNCTION__);
            }
            return $response;
        } catch (\Exception $exception) {
            // Handle error.
            return NULL;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function modifyReservation($params) {
        // Instantiate SOAP client
        $this->initializeClient('http://htng.org/1.1/Header/', [
            'OTA_HotelResModifyRQ' => 'GurwinderAntal\crs\Type\Request\OTA_HotelResModifyRQ',
            'OTA_HotelResModifyRS' => 'GurwinderAntal\crs\Type\Response\OTA_HotelResModifyRS',
        ]);

        // Build POS->Source->RequestorID->CompanyName
        $companyName = new CompanyName(
            $params['CodeContext'] ?? NULL,
            $params['CompanyShortName'] ?? NULL,
            $params['TravelSelector'] ?? NULL,
            $params['POS']['Code'] ?? NULL
        );
        // Build POS->Source->RequestorID
        $requestorId = new RequestorID(
            $companyName,
            NULL,
            $params['POS']['ID'] ?? NULL,
            $params['POS']['ID_Context'] ?? NULL,
            $params['Instance'] ?? NULL,
            $params['PinNumber'] ?? NULL,
            $params['MessagePassword'] ?? NULL
        );
        // Build POS->Source
        $source = new Source(
            NULL,
            $requestorId
        );
        // Build OTA_CancelRQ->POS
        $pos = new POS($source);
        // Build HotelResModify->RoomStay->RoomTypes
        $roomTypes = [
            new RoomType(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $params['IsRoom'] ?? NULL,
                $params['RoomTypeCode'] ?? NULL,
                $params['InvBlockCode'] ?? NULL,
                $params['NumberOfUnits'] ?? NULL,
                NULL
            ),
        ];
        // Build HotelResModify->RoomStay->RatePlans
        $ratePlans = [
            new RatePlan(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $params['MealsIncluded'] ?? NULL,
                $params['RatePlanCode'] ?? NULL,
                $params['RatePlanName'] ?? NULL,
                $params['AccrualIndicator'] ?? NULL,
                $params['AutoEnrollmentIndicator'] ?? NULL,
                $params['BookingCode'] ?? NULL,
                $params['RatePlanType'] ?? NULL,
                $params['RatePlanID'] ?? NULL,
                $params['EffectiveDate'] ?? NULL,
                $params['ExpireDate'] ?? NULL,
                $params['CurrencyCode'] ?? NULL,
                $params['TaxInclusive'] ?? NULL,
                $params['PrepaidIndicator'] ?? NULL,
                $params['RatePlanCategory'] ?? NULL,
                $params['AvailabilityStatus'] ?? NULL,
                $params['PriceViewableInd'] ?? NULL
            ),
        ];
        // Build HotelResModify->RoomStay->GuestCounts->GuestCount
        $guestCount = [];
        foreach ($params['Count'] as $aqc => $count) {
            $aqc = 'self::AQC_' . strtoupper($aqc);
            $guestCount[] = new GuestCount(constant($aqc), $count, NULL);
        }
        // Build HotelResModify->RoomStay->GuestCounts
        $guestCounts = new GuestCounts(
            $guestCount,
            $params['IsPerRoom'] ?? NULL
        );
        // Build HotelResModify->RoomStay->TimeSpan
        $timeSpan = new DateTimeSpan(
            $params['Start'] ?? NULL,
            $params['End'] ?? NULL,
            $params['Duration'] ?? NULL,
            NULL
        );
        // Build HotelResModify->RoomStay->BasicPropertyInfo
        $basicPropertyInfo = new HotelReferenceGroup(
            $params['HotelCode'] ?? NULL,
            $params['HotelName'] ?? NULL,
            $params['AreaID'] ?? NULL,
            $params['HotelCodeContext'] ?? NULL,
            $params['ChainCode'] ?? NULL,
            $params['ChainName'] ?? NULL,
            $params['BrandCode'] ?? NULL,
            $params['BrandName'] ?? NULL,
            $params['HotelCityCode'] ?? NULL
        );
        // Build HotelReservation->RoomStay->SpecialRequests
        if (array_key_exists('SpecialRequests', $params)) {
            $specialRequests = [];
            foreach ($params['SpecialRequests'] as $specialRequest) {
                $specialRequests[] = new SpecialRequest(
                    $specialRequest['Text'] ?? NULL,
                    $specialRequest['Name'] ?? NULL,
                    $specialRequest['RequestCode'] ?? NULL,
                    $specialRequest['Description'] ?? NULL
                );
            }
        }
        else {
            $specialRequests = NULL;
        }
        // Add membership info
        if (array_key_exists('MembershipID', $params['ResGuests'][0]) && !empty($params['ResGuests'][0]['MembershipID'])) {
            $memberships = [
                new Membership(
                    $params['ProgramID'] ?? NULL,
                    $params['BonusCode'] ?? NULL,
                    $params['ResGuests'][0]['AccountID'] ?? NULL,
                    $params['ResGuests'][0]['MembershipID'] ?? NULL,
                    $params['TravelSector'] ?? NULL,
                    $params['PointsEarned'] ?? NULL
                ),
            ];
        }
        else {
            $memberships = NULL;
        }
        // Build HotelResModify->RoomStays
        $roomStays = [
            new RoomStay(
                NULL,
                NULL,
                NULL,
                NULL,
                $roomTypes,
                $ratePlans,
                NULL,
                $guestCounts,
                $timeSpan,
                $specialRequests,
                $basicPropertyInfo,
                NULL,
                NULL,
                NULL,
                NULL,
                $memberships,
                $params['MarketCode'] ?? NULL,
                $params['SourceOfBusiness'] ?? NULL,
                $params['IndexNumber'] ?? NULL
            ),
        ];
        $resGuests = [];
        foreach ($params['ResGuests'] as $resGuest) {
            // Build HotelResModify->ResGuest->Profiles->Profile->Customer
            $customer = new Customer(
                new PersonName(
                    $resGuest['NamePrefix'] ?? NULL,
                    $resGuest['NameTitle'] ?? NULL,
                    $resGuest['GivenName'] ?? NULL,
                    $resGuest['MiddleName'] ?? NULL,
                    $resGuest['Surname'] ?? NULL,
                    $resGuest['NameSuffix'] ?? NULL,
                    $resGuest['NameType'] ?? NULL
                ),
                new Telephone(
                    $resGuest['FormattedInd'] ?? FALSE,
                    $resGuest['PhoneTechType'] ?? NULL,
                    $resGuest['PhoneNumber'] ?? NULL,
                    $resGuest['PhoneUseType'] ?? NULL,
                    $resGuest['DefaultInd'] ?? FALSE
                ),
                $resGuest['Email'] ?? NULL,
                new AddressInfo(
                    $resGuest['AddressLine'] ?? NULL,
                    $resGuest['CityName'] ?? NULL,
                    $resGuest['PostalCode'] ?? NULL,
                    new StateProv($resGuest['StateCode'] ?? NULL),
                    new CountryName($resGuest['Code'] ?? NULL),
                    $resGuest['Type'] ?? NULL,
                    $resGuest['Remark'] ?? NULL,
                    $resGuest['CompanyName'] ?? NULL,
                    $resGuest['FormattedInd'] ?? FALSE,
                    $resGuest['DefaultInd'] ?? FALSE
                ),
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $resGuest['BirthDate'] ?? NULL,
                $resGuest['Gender'] ?? NULL,
                $resGuest['CustomerValue'] ?? NULL,
                $resGuest['LockoutType'] ?? NULL,
                $resGuest['Language'] ?? NULL
            );
            // Build HotelResModify->ResGuest->Profiles->Profile
            $profile = new Profile(
                NULL,
                NULL,
                $customer,
                NULL,
                NULL,
                NULL,
                NULL,
                $resGuest['ProfileType'] ?? NULL,
                NULL,
                NULL,
                NULL,
                $resGuest['ShareAllMarketInd'] ?? NULL
            );
            // Build HotelResModify->ResGuest->Profiles
            $profiles = [
                new ProfileInfo(
                    NULL,
                    $profile,
                    NULL
                ),
            ];
            // Build HotelResModify->ResGuests
            $resGuests[] = new ResGuest(
                NULL,
                $profiles,
                NULL,
                NULL,
                $params['PrimaryIndicator'] ?? NULL,
                $params['RPH'] ?? NULL,
                NULL
            );
        }
        // Add any comments
        if (array_key_exists('Comments', $params)) {
            $comments = [];
            foreach ($params['Comments'] as $comment) {
                $comments[] = new Comment($comment['Text']);
            }
        }
        else {
            $comments = NULL;
        }
        if ($this->array_keys_exist([
            'CardCode',
            'CardNumber',
            'CardExpireDate',
            'SeriesCode',
        ], $params)) {
            // Build HotelResModify->ResGlobalInfo->Guarantee->GuaranteesAccepted
            $guaranteesAccepted = [
                new GuaranteeAccepted(
                    new PaymentCard(
                        $params['CardHolderName'] ?? NULL,
                        NULL,
                        NULL,
                        $params['CardType'] ?? NULL,
                        $params['CardCode'] ?? NULL,
                        $params['CardNumber'] ?? NULL,
                        $params['SeriesCode'] ?? NULL,
                        $params['CardExpireDate'] ?? NULL
                    ),
                    NULL,
                    NULL
                ),
            ];
            // Build HotelResModify->ResGlobalInfo->Guarantee
            $guarantee = new Guarantee(
                $guaranteesAccepted,
                NULL,
                NULL,
                NULL,
                NULL
            );
            // Build HotelResModify->ResGlobalInfo
            $resGlobalInfo = new ResGlobalInfo(
                NULL,
                $guarantee,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            );
        }
        else {
            $resGlobalInfo = NULL;
        }
        // Build OTA_HotelResModifyRQ->HotelResModify->Verification
        $Verification = new Verification(
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            new TPA_Extensions(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $basicPropertyInfo,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            )
        );
        // Build OTA_HotelResModifyRQ->HotelResModifies->HotelResModify->UniqueID
        $uniqueId = new UniqueID(
            NULL,
            self::UIT_RESERVATION,
            $params['ID'] ?? NULL,
            'CrsConfirmNumber',
            NULL,
            NULL
        );
        // Build OTA_HotelResModifyRQ->WrittenConfInst
        $writtenConfInst = array_key_exists('EmailTemplate', $params) ?
            new WrittenConfInst(
                new SupplementalData(
                    NULL,
                    $params['EmailTemplate'],
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL),
                NULL,
                NULL,
                NULL,
                NULL
            ) : NULL;
        // Build OTA_HotelResRQ->HotelReservations->Services
        if (array_key_exists('Services', $params)) {
            $services = [];
            foreach ($params['Services'] as $service) {
                $serviceDetails = new ResCommonDetailType(
                    new GuestCounts(
                        [
                            new GuestCount(
                                self::AQC_ADULT,
                                $service['Quantity'] ?? NULL,
                                NULL
                            ),
                        ],
                        $params['IsPerRoom'] ?? NULL
                    ),
                    NULL,
                    new DateTimeSpanType(
                        $params['Start'] ?? NULL,
                        $params['End'] ?? NULL,
                        NULL,
                        NULL
                    ),
                    NULL,
                    NULL
                );
                $services[] = new Service(
                    $serviceDetails,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    $service['Quantity'] ?? NULL,
                    $service['Inclusive'] ?? NULL,
                    $service['ServiceInventoryCode'] ?? NULL,
                    $service['ServicePricingType'] ?? NULL,
                    $service['ServiceRPH'] ?? NULL
                );
            }
        }
        else {
            $services = NULL;
        }
        // Build OTA_HotelResModifyRQ->HotelResModifies
        $HotelResModifies = [
            new HotelResModify(
                $uniqueId,
                $roomStays,
                $resGuests,
                $resGlobalInfo,
                NULL,
                $writtenConfInst,
                $services,
                NULL,
                NULL,
                TRUE,
                $params['CreatorID'] ?? NULL,
                NULL,
                $params['LastModifierID'] ?? NULL,
                NULL,
                $Verification
            ),
        ];
        // Build request
        $request = new OTA_HotelResModifyRQ(
            $params['EchoToken'] ?? NULL,
            $params['PrimaryLangID'] ?? NULL,
            $params['AltLangID'] ?? NULL,
            $this->timestamp(),
            $params['Target'] ?? NULL,
            $params['Version'] ?? NULL,
            $params['MessageContentCode'] ?? NULL,
            NULL,
            $pos,
            $HotelResModifies
        );

        try {
            $response = $this->client->ModifyReservations($request);
            if ($this->debug) {
                $this->logMessage(__FUNCTION__);
            }
            return $response;
        } catch (\Exception $exception) {
            // Handle error.
            return NULL;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancelReservation($params) {
        // Instantiate SOAP client
        $this->initializeClient('http://htng.org/1.1/Header/', [
            'OTA_CancelRQ' => 'GurwinderAntal\crs\Type\Request\OTA_CancelRQ',
            'OTA_CancelRS' => 'GurwinderAntal\crs\Type\Response\OTA_CancelRS',
        ]);

        // Build POS->Source->RequestorID->CompanyName
        $companyName = new CompanyName(
            $params['CodeContext'] ?? NULL,
            $params['CompanyShortName'] ?? NULL,
            $params['TravelSelector'] ?? NULL,
            $params['POS']['Code'] ?? NULL
        );
        // Build POS->Source->RequestorID
        $requestorId = new RequestorID(
            $companyName,
            NULL,
            $params['POS']['ID'] ?? NULL,
            $params['POS']['ID_Context'] ?? NULL,
            $params['Instance'] ?? NULL,
            $params['PinNumber'] ?? NULL,
            $params['MessagePassword'] ?? NULL
        );
        // Build POS->Source
        $source = new Source(
            NULL,
            $requestorId
        );
        // Build OTA_CancelRQ->POS
        $pos = new POS($source);

        // Build OTA_CancelRQ->UniqueID
        $uniqueId = new UniqueID(
            NULL,
            self::UIT_RESERVATION,
            $params['ID'] ?? NULL,
            'CrsConfirmNumber',
            NULL,
            NULL
        );

        // Build OTA_CancelRQ->Verification
        $verification = new Verification(
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            new TPA_Extensions(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                new HotelReferenceGroup(
                    $params['HotelCode'] ?? NULL,
                    NULL,
                    NULL,
                    NULL,
                    $params['ChainCode'] ?? NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL
                ),
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            )
        );
        // Build OTA_CancelRQ->TPA_Extensions
        if (array_key_exists('EmailTemplate', $params)) {
            $writtenConfInst = new WrittenConfInst(
                new SupplementalData(
                    NULL,
                    $params['EmailTemplate'],
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL),
                NULL,
                NULL,
                NULL,
                NULL
            );
            $tpaExtension = new TPA_Extensions(
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                $writtenConfInst,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            );
        }
        else {
            $tpaExtension = NULL;
        }
        // Build request
        $request = new OTA_CancelRQ(
            $params['EchoToken'] ?? NULL,
            $params['PrimaryLangID'] ?? NULL,
            $params['AltLangID'] ?? NULL,
            $this->timestamp(),
            $params['Target'] ?? NULL,
            $params['Version'] ?? NULL,
            $params['MessageContentCode'] ?? NULL,
            $tpaExtension,
            $uniqueId,
            $verification,
            $pos,
            NULL,
            NULL,
            NULL
        );

        try {
            $response = $this->client->CancelReservations($request);
            if ($this->debug) {
                $this->logMessage(__FUNCTION__);
            }
            return $response;
        } catch (\Exception $exception) {
            // Handle error.
            return NULL;
        }
    }

  /**
   * @param $params
   *
   * @return \GurwinderAntal\crs\Type\Response\OTA_HotelDescriptiveInfoRS|null
   */
    public function getHotelDetails($params) {
        // Instantiate SOAP client
        $this->initializeClient('http://htng.org/1.1/Header/', [
            'OTA_HotelDescriptiveInfoRQ' => 'GurwinderAntal\crs\Type\Request\OTA_HotelDescriptiveInfoRQ',
            'OTA_HotelDescriptiveInfoRS' => 'GurwinderAntal\crs\Type\Response\OTA_HotelDescriptiveInfoRS',
        ]);

        // Build POS->Source->RequestorID->CompanyName
        $companyName = new CompanyName(
            $params['CodeContext'] ?? NULL,
            $params['CompanyShortName'] ?? NULL,
            $params['TravelSelector'] ?? NULL,
            $params['POS']['Code'] ?? NULL
        );
        // Build POS->Source->RequestorID
        $requestorId = new RequestorID(
            $companyName,
            NULL,
            $params['POS']['ID'] ?? NULL,
            $params['POS']['ID_Context'] ?? NULL,
            $params['Instance'] ?? NULL,
            $params['PinNumber'] ?? NULL,
            $params['MessagePassword'] ?? NULL
        );
        // Build POS->Source
        $source = new Source(
            NULL,
            $requestorId
        );
        // Build OTA_HotelDescriptoveInfoRQ->POS
        $pos = new POS($source);

        // Build HotelDescriptiveInfos->Policies
        $policies = new Policies(
            NULL,
            $params['SendPolicies'] ?? NULL
        );
        // Build OTA_HotelDescriptoveInfoRQ->HotelDescriptiveInfos
        $hotelDescriptiveInfos = [
            new HotelDescriptiveInfo(
                NULL,
                NULL,
                $policies,
                NULL,
                NULL,
                NULL,
                NULL,
                $params['ChainCode'] ?? NULL,
                $params['BrandCode'] ?? NULL,
                $params['HotelCode'] ?? NULL,
                $params['HotelCityCode'] ?? NULL,
                $params['HotelName'] ?? NULL,
                $params['HotelCodeContext'] ?? NULL
            ),
        ];

        // Build request
        $request = new OTA_HotelDescriptiveInfoRQ(
            $params['EchoToken'] ?? NULL,
            $params['PrimaryLangID'] ?? NULL,
            $params['AltLangID'] ?? NULL,
            $this->timestamp(),
            $params['Target'] ?? NULL,
            $params['Version'] ?? NULL,
            $params['MessageContentCode'] ?? NULL,
            NULL,
            $hotelDescriptiveInfos,
            $pos
        );
        try {
            $response = $this->client->GetHotelDetails($request);
            if ($this->debug) {
                $this->logMessage(__FUNCTION__);
            }
            return $response;
        } catch (\Exception $exception) {
            // Handle error.
            return NULL;
        }
    }

    /**
     * Logs request and response messages in XML format in the files directory.
     *
     * @param $operation
     *    The operation being performed, eg. createReservation.
     */
    public function logMessage($operation) {
        $dir = $_SERVER['DOCUMENT_ROOT']. '/sites/default/files/messages';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, TRUE);
        }
        $reqFile = fopen($dir . '/synxis_' . $operation . '_request_' . time() . '.xml', 'w');
        fwrite($reqFile, $this->client->__getLastRequest());
        fclose($reqFile);
        $resFile = fopen($dir . '/synxis_' . $operation . '_response_' . time() . '.xml', 'w');
        fwrite($resFile, $this->client->__getLastResponse());
        fclose($resFile);
    }

}
