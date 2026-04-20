<?php

defined( 'ABSPATH' ) || die();

// Load Feed Add-On Framework.
GFForms::include_feed_addon_framework();

class GFAPITrap extends GFFeedAddOn {
 
    protected $_version = '1.0.0';
    protected $_min_gravityforms_version = '2.8';
    protected $_slug = 'gravity-api-trap';
    protected $_path = 'gravity-api-trap/gravity-api-trap.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms Enquire/Aline Integration';
    protected $_short_title = 'Enquire/Aline API';
 
    private static $_instance = null;
 
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFAPITrap();
        }
 
        return self::$_instance;
    }

    public function plugin_page() {
    }

    public function feed_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Enquire/Aline Settings', 'gravity-api-trap' ),
                'fields' => array(
                    array(
                        'name'                => 'feedName',
                        'label'               => '<h3>Feed Label</h3>',
                        'type'                => 'text',
                    ),
                    array(
                        'name'                => 'formFieldMap',
                        'label'               => '<h3>' . esc_html__( 'Map API Fields to Form Fields', 'gravity-api-trap' ) . '</h3>',
                        'type'                => 'generic_map',
                        'key_field'           => array(
                            'title'             => 'API Field',
                            'allow_custom'      => FALSE,
                            'choices'           => array(
                                array('label' => 'Email','value' => 'Email',),
                                array('label' => 'FirstName','value' => 'FirstName',),
                                array('label' => 'LastName','value' => 'LastName',),
                                array('label' => 'Phone','value' => 'phone',),
                                array('label' => 'Comments','value' => 'Message',),
                                array('label' => 'CommunityUnique','value' => 'communityunique',),
                                array('label' => 'InquiringFor','value' => 'inquiringfor',),
                                array('label' => 'utmSource','value' => 'utmsource',),
                                array('label' => 'utmMedium','value' => 'utmmedium',),
                                array('label' => 'utmCampaign','value' => 'utmcampaign',),
                                array('label' => 'utmId', 'value' => 'utmid',),
                                array('label' => 'GCLID','value' => 'gclid'),
                                array('label' => 'FBCLID','value' => 'fbclid'),
                                array('label' => 'GAD Parameter','value' => 'gad'),
                                array('label' => 'GAD Parameter 2','value' => 'gad_2'),
                                array('label' => 'GAD Source','value' => 'gad_source'),
                                array('label' => 'display','value' => 'display'),
                                array('label' => 'Interested In','value' => 'careLevel',),
                                array('label' => 'MarketSource', 'value' => 'marketsource',),
                                array('label' => 'Number Attending', 'value' => 'NumberAttending',),
                                array('label' => 'Event Name', 'value' => 'eventname',),
                                array('label' => 'Event Date', 'value' => 'eventdate',),
                                array('label' => 'NO Trigger', 'value' => 'notrigger',),
                                array('label' => 'Request a Tour', 'value' => 'RequestTour',),
                                array('label' => 'Request a Community Brochure', 'value' => 'RequestBrochure',),
                                array('label' => 'Request Special Pricing Information', 'value' => 'RequestPricingInfo',),
                                array('label' => 'Knowledge Is Comfort', 'value' => 'KnowledgeComfort',),
                                array('label' => 'Seeking Support', 'value' => 'SeekingSupport',),
                                array('label' => 'Empowering Your Retirement', 'value' => 'EmpoweringRetirement',),
                                array('label' => 'Source URL', 'value' => 'SourceURL',),
								array('label' => 'DoNotCall', 'value' => 'DoNotCall',),
								array('label' => 'DoNotText', 'value' => 'DoNotText',),
								array('label' => 'DoNotEmail', 'value' => 'DoNotEmail',),
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    public function feed_list_columns() {
        return array(
            'feedName' => __( 'Name', 'gravity-api-trap' ),
        );
    }

    public function process_feed( $feed, $entry, $form ) {
        // Intentionally avoid runtime dumps/echo output in submission flow.
        // Any direct output here can break expected GF redirects/confirmation behavior.

        // Build a flattened view of feed settings for optional troubleshooting.
        // We keep this structure for maintainability, but logging is disabled by default.
        $feedData = array();
        foreach ($feed as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $feedData[$key . '_' . $subKey] = $subValue;
                }
            } else {
                $feedData[$key] = $value;
            }
        }
        error_log('Feed data: ' . print_r($feedData, true), 3, plugin_dir_path(__FILE__) . 'debug.log');

        // Resolve mapped Gravity Forms values into API fields for this submission.
        $metaData = $this->get_generic_map_fields( $feed, 'formFieldMap' );

        $stoptrigger = isset($metaData['notrigger']) ? $this->get_field_value($form, $entry, $metaData['notrigger']) : null;
        $rawCareLevel = isset($metaData['careLevel']) ? $this->get_field_value($form, $entry, $metaData['careLevel']) : null;

        // Early bypass path: only skip API submission when "No Trigger" is exactly "True".
        if ( $this->should_bypass_api_submission( $stoptrigger, $rawCareLevel ) ) {
            // error_log(
            //     'Bypassing Enquire/Aline API request. Stop Trigger: "' . $this->normalize_submission_value( $stoptrigger ) .
            //     '" Care Level: "' . $this->normalize_submission_value( $rawCareLevel ) . '"',
            //     3,
            //     plugin_dir_path(__FILE__) . 'debug.log'
            // );
            return;
        }
    
        // Normalize care level values to a small known set before API payload assembly.
        $careLevelMap = [
            'Independent Living' => 'Independent Living',
            'Assisted Living' => 'Assisted Living',
            'Memory Care' => 'Memory Care',
            'Short Term Stay' => 'Short Term Stay',
            'Unsure' => 'Unsure',
        ];

        // Apply defaults for required fields if mapping is missing or empty.
        $communityunique = isset($metaData['communityunique']) ? $this->get_field_value($form, $entry, $metaData['communityunique']) : null;
        if (!$communityunique) {
            $communityunique = 'CSL003LAND';
        }

        $carelevel = $rawCareLevel;
        $carelevel = isset($careLevelMap[$carelevel]) ? $careLevelMap[$carelevel] : $carelevel;
        
        // Check if $carelevel is still null
        if ($carelevel === null) {
            error_log('Care level value is null', 3, plugin_dir_path(__FILE__) . 'debug.log');
            $carelevel = 'Assisted Living';
        }


        $marketsource = isset($metaData['marketsource']) ? $this->get_field_value($form, $entry, $metaData['marketsource']) : null;

        $Email = isset($metaData['Email']) ? $this->get_field_value($form, $entry, $metaData['Email']) : null;
        $first = isset($metaData['FirstName']) ? $this->get_field_value($form, $entry, $metaData['FirstName']) : null;
        $last = isset($metaData['LastName']) ? $this->get_field_value($form, $entry, $metaData['LastName']) : null;
        $phone  = isset($metaData['phone']) ? preg_replace('/\D/', '', $this->get_field_value($form, $entry, $metaData['phone'])) : null;

        $inquiringfor = isset($metaData['inquiringfor']) ? $this->get_field_value($form, $entry, $metaData['inquiringfor']) : null;

        // Normalize attribution-style fields so literal placeholders (for example "null")
        // are not forwarded to the CRM as if they were real values.
        $utmsource = isset($metaData['utmsource']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['utmsource']) ) : null;
        $utmcampaign = isset($metaData['utmcampaign']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['utmcampaign']) ) : null;
        $utmmedium = isset($metaData['utmmedium']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['utmmedium']) ) : null;
        $utmid = isset($metaData['utmid']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['utmid']) ) : null;
        $gclid = isset($metaData['gclid']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['gclid']) ) : null;
        $fbclid = isset($metaData['fbclid']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['fbclid']) ) : null;
        $gad = isset($metaData['gad']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['gad']) ) : null;
        $gad_2 = isset($metaData['gad_2']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['gad_2']) ) : null;
        $gad_source = isset($metaData['gad_source']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['gad_source']) ) : null;
        $display = isset($metaData['display']) ? $this->normalize_tracking_value( $this->get_field_value($form, $entry, $metaData['display']) ) : null;

        // If the marketsource field was empty (JS didn't set it or field not mapped),
        // derive it server-side from the UTM params already in the entry.
        if ( empty( $marketsource ) ) {
            $marketsource = $this->derive_market_source( $utmsource, $utmmedium, $utmcampaign, $utmid, $gclid, $fbclid, $gad, $gad_source, $display );
            if ( $marketsource ) {
                error_log( 'MarketSource derived server-side: ' . $marketsource, 3, plugin_dir_path( __FILE__ ) . 'debug.log' );
            }
        }

        $eventname = isset($metaData['eventname']) ? $this->get_field_value($form, $entry, $metaData['eventname']) : null;
        $eventdate = isset($metaData['eventdate']) ? $this->get_field_value($form, $entry, $metaData['eventdate']) : null;
        $numberattending = isset($metaData['NumberAttending']) ? $this->get_field_value($form, $entry, $metaData['NumberAttending']) : null;
        $guest1 = isset($metaData['Guest1']) ? $this->get_field_value($form, $entry, $metaData['Guest1']) : null;
        $guest2 = isset($metaData['Guest2']) ? $this->get_field_value($form, $entry, $metaData['Guest2']) : null;
        $guest3 = isset($metaData['Guest3']) ? $this->get_field_value($form, $entry, $metaData['Guest3']) : null;

        // Optional note/engagement fields are appended into the API comments block.
        $requesttour = isset($metaData['RequestTour']) ? $this->get_field_value($form, $entry, $metaData['RequestTour']) : null;
        $requestbrochure = isset($metaData['RequestBrochure']) ? $this->get_field_value($form, $entry, $metaData['RequestBrochure']) : null;
        $requestpricing = isset($metaData['RequestPricingInfo']) ? $this->get_field_value($form, $entry, $metaData['RequestPricingInfo']) : null;
        $knowledgecomfort = isset($metaData['KnowledgeComfort']) ? $this->get_field_value($form, $entry, $metaData['KnowledgeComfort']) : null;
        $seekingsupport = isset($metaData['SeekingSupport']) ? $this->get_field_value($form, $entry, $metaData['SeekingSupport']) : null;
        $empoweringretirement = isset($metaData['EmpoweringRetirement']) ? $this->get_field_value($form, $entry, $metaData['EmpoweringRetirement']) : null;
        $sourceurl = isset($metaData['SourceURL']) ? $this->get_field_value($form, $entry, $metaData['SourceURL']) : null;
		$donotcall = isset($metaData['DoNotCall']) ? $this->get_field_value($form, $entry, $metaData['DoNotCall']) : null;
		$donottext = isset($metaData['DoNotText']) ? $this->get_field_value($form, $entry, $metaData['DoNotText']) : null;
		$donotemail = isset($metaData['DoNotEmail']) ? $this->get_field_value($form, $entry, $metaData['DoNotEmail']) : null;

        // Always set to prospect since it's always "Myself"
        $primaryContactId = 'prospect';
        error_log('Primary contact ID: ' . $primaryContactId, 3, plugin_dir_path(__FILE__) . 'debug.log');

        // Build a single comments payload so Aline users can see all submitted context.
        $originalComments = isset($metaData['Message']) ? $this->get_field_value($form, $entry, $metaData['Message']) : '';
        $comments = 
                "Original Comments: " . $originalComments . "\n" .
                "Event Name: " . $eventname . "\n" .
                "Event Date: " . $eventdate . "\n" .
                "Community: " . $communityunique . "\n" .
                "Email: " . $Email . "\n" .
                "Name: " . $first . " " . $last . "\n" .
                "Phone: " . $phone . "\n" .
                "Inquiring For: " . $inquiringfor . "\n" .
                "UTM Source: " . $utmsource . "\n" .
                "UTM Campaign: " . $utmcampaign . "\n" .
                "UTM Medium: " . $utmmedium . "\n" .
                "UTM ID: " . $utmid . "\n" .
                "GCLID: " . $gclid . "\n" .
                "FBCLID: " . $fbclid . "\n" .
                "?gad: " . $gad . "\n" .
                "&gad: " . $gad_2 . "\n" .
                "gad_source: " . $gad_source . "\n" .
                "display: " . $display . "\n" .
                "Market Source: " . $marketsource . "\n" .
                "Care Level: " . $carelevel . "\n" .
                "Primary Contact ID: " . $primaryContactId . "\n" .
                "Number Attending: " . $numberattending . "\n" .
                "Request Tour " . $requesttour . "\n" . 
                "Request Brochure " . $requestbrochure . "\n" . 
                "Request Pricing Info " . $requestpricing . "\n" .
                "Knowledge is Comfort " . $knowledgecomfort  . "\n" .
                "Seeking Support " . $seekingsupport . "\n" .
                "Empowering Retirement " . $empoweringretirement . "\n" . 
                "Source URL " . $sourceurl . "\n" .
				"DoNotCall " . $donotcall . "\n" .
				"DoNotText " . $donottext . "\n" .
				"DoNotEmail " . $donotemail . "\n";

        // Compose the final API payload from mapped/normalized values.
        $data = array(
            'communityunique' => $communityunique,
            'Email' => $Email,
            'FirstName' => $first,
            'LastName' => $last,
            'Phone' => $phone,
            'Message' => $comments,
            'utmsource' => $utmsource,
            'utmcampaign' => $utmcampaign,
            'utmmedium' => $utmmedium,
            'utmid' => $utmid,
            'gclid' => $gclid,
            'fbclid' => $fbclid,
            'gad' => $gad,
            'gad_2' => $gad_2,
            'gad_source' => $gad_source,
            'display' => $display,
            'marketsource' => $marketsource,
            'carelevel' => $carelevel,
            'primarycontactid' => $primaryContactId,
            'requesttour' => $requesttour,
            'requestbrochure' => $requestbrochure,
            'requestpricing' => $requestpricing,
            'knowledgecomfort' => $knowledgecomfort,
            'seekingsupport' => $seekingsupport,
            'empoweringretirement' => $empoweringretirement,
            'sourceurl' => $sourceurl,
			'donotcall' => $donotcall,
			'donottext' => $donottext,
			'donotemail' => $donotemail
        );

        // Debug-only payload tracing disabled for production stability/noise control.
        // error_log('this is the data: ' . print_r($data, true));

        // Handoff to API layer; this is where success/failure outcome logs are retained.
        $response = $this->sendApiRequest($data);
        // error_log('this is the response: ' . print_r($response, true));
    }

    private function should_bypass_api_submission( $stoptrigger, $careLevel ) {
        $triggerVal = strtolower( $this->normalize_submission_value( $stoptrigger ) );
        $careVal    = strtolower( $this->normalize_submission_value( $careLevel ) );

        error_log( 'Bypass check — noTrigger: "' . $triggerVal . '" | careLevel: "' . $careVal . '"', 3, plugin_dir_path( __FILE__ ) . 'debug.log' );

        if ( 'true' === $triggerVal ) {
            error_log( 'Bypassing: noTrigger field is true', 3, plugin_dir_path( __FILE__ ) . 'debug.log' );
            return true;
        }

        if ( strpos( $careVal, 'employ' ) !== false || strpos( $careVal, 'job' ) !== false ) {
            error_log( 'Bypassing: careLevel contains employment keyword', 3, plugin_dir_path( __FILE__ ) . 'debug.log' );
            return true;
        }

        error_log( 'Not bypassing — proceeding with API submission', 3, plugin_dir_path( __FILE__ ) . 'debug.log' );
        return false;
    }

    private function normalize_submission_value( $value ) {
        if ( is_array( $value ) ) {
            $value = implode(
                ', ',
                array_filter(
                    array_map(
                        'trim',
                        array_map( 'strval', $value )
                    ),
                    'strlen'
                )
            );
        }

        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }

        if ( null === $value ) {
            return '';
        }

        return trim( (string) $value );
    }

    private function normalize_comparison_value( $value ) {
        return strtolower( $this->normalize_submission_value( $value ) );
    }

    private function normalize_tracking_value( $value ) {
        $normalized = $this->normalize_submission_value( $value );
        $lowerValue = strtolower( $normalized );

        // Some integrations send placeholders like "null" or "(none)".
        // Convert those to empty strings so downstream systems treat them as missing.
        if ( '' === $normalized || 'null' === $lowerValue || '(not set)' === $lowerValue || '(none)' === $lowerValue ) {
            return '';
        }

        return $normalized;
    }

    private function derive_market_source( $source, $medium, $campaign, $utm_id, $gclid, $fbclid, $gad, $gad_source, $display ) {
        $source   = strtolower( (string) $source );
        $medium   = strtolower( (string) $medium );
        $campaign = strtolower( (string) $campaign );
        $utm_id   = strtolower( (string) $utm_id );
        $display  = strtolower( (string) $display );

        $is_google    = strpos( $source, 'google' ) !== false || $source === 'g';
        $is_bing      = strpos( $source, 'bing' ) !== false || strpos( $source, 'microsoft' ) !== false;
        $is_facebook  = strpos( $source, 'facebook' ) !== false || $source === 'fb';
        $is_instagram = strpos( $source, 'instagram' ) !== false;

        $is_email   = in_array( $medium, [ 'email', 'newsletter', 'e-mail' ] );
        $is_organic = in_array( $medium, [ 'organic', 'seo' ] );
        $is_display = in_array( $medium, [ 'display', 'banner', 'programmatic' ] ) ||
                      strpos( $campaign, 'display' ) !== false ||
                      $display === '1' || $display === 'true';
        $is_local   = strpos( $campaign, 'local' ) !== false || strpos( $medium, 'local' ) !== false;

        $paid_mediums = [ 'cpc','ppc','sem','paid','paid_search','paidsearch','paid-social','paidsocial','paid social','display','banner','programmatic' ];
        $looks_paid   = in_array( $medium, $paid_mediums ) ||
                        strpos( $medium, 'paid' ) !== false ||
                        strpos( $medium, 'cpc' )  !== false ||
                        strpos( $medium, 'ppc' )  !== false;

        $has_google_click = $gclid || $gad || $gad_source;
        $has_meta_click   = (bool) $fbclid;

        if ( $has_google_click ) {
            if ( $is_display ) return 'WEB - Google Display';
            if ( $is_local )   return 'WEB - Google Local';
            return 'WEB - Google Paid';
        }

        if ( $has_meta_click ) {
            if ( $is_organic ) return 'WEB - Organic Social';
            if ( $looks_paid ) return 'WEB - Facebook Paid';
            return 'WEB - Facebook';
        }

        if ( $is_email ) return 'Email';

        if ( $looks_paid && ( strpos( $medium, 'cpc' ) !== false || strpos( $medium, 'ppc' ) !== false || strpos( $medium, 'sem' ) !== false || strpos( $medium, 'paid_search' ) !== false || strpos( $medium, 'paidsearch' ) !== false ) ) {
            if ( $is_google ) return 'WEB - Google Paid';
            if ( $is_bing )   return 'WEB - Bing Search';
            return 'WEB - Paid Search';
        }

        if ( $looks_paid && ( strpos( $medium, 'social' ) !== false || $is_facebook || $is_instagram ) ) {
            if ( $is_facebook || $is_instagram ) return 'WEB - Facebook Paid';
            return 'WEB - Paid Social';
        }

        $social_sources = [ 'facebook','instagram','linkedin','tiktok','twitter','x','pinterest','youtube' ];
        if ( in_array( $source, $social_sources ) ) return 'WEB - Organic Social';

        if ( $is_organic ) {
            $is_local_listing = $source === 'local_listing' || strpos( $source, 'local_listing' ) !== false || $utm_id === 'gbp';
            if ( $is_local_listing ) return 'WEB - Organic Search';
            if ( $is_google ) return 'WEB - Google Search';
            if ( $is_bing )   return 'WEB - Bing Search';
            return 'WEB - Organic Search';
        }

        if ( $is_display ) {
            if ( $is_google ) return 'WEB - Google Display';
            return 'WEB - 3rd Party Display';
        }

        if ( $source || $medium || $campaign ) return 'Internet';

        return 'WEB - Company Website';
    }

    public function sendApiRequest(array $data) {
        // Check if required settings are configured
        $primaryApiKey = get_option('gravity_api_trap_primary_api_key');
        $url = get_option('gravity_api_trap_endpoint_url');
        $portalId = get_option('gravity_api_trap_portal_id');

        if (empty($primaryApiKey) || empty($url) || empty($portalId)) {
            error_log('API request failed: Missing required settings (API key, URL, or Portal ID)', 3, plugin_dir_path(__FILE__) . 'debug.log');
            return false;
        }

        // Validate required data
        if (empty($data['Email'])) {
            error_log('API request failed: Email is required', 3, plugin_dir_path(__FILE__) . 'debug.log');
            return false;
        }

        // Debug-only request dumps are intentionally disabled.
        // error_log('API request data: ' . print_r($data, true), 3, plugin_dir_path(__FILE__) . 'debug.log');
    
        $getResponse = wp_remote_get($url . '?Email=' . $data['Email'], [
            'method' => 'GET',
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $primaryApiKey,
                'Content-Type' => 'application/json',
                'PortalId'     => get_option('gravity_api_trap_portal_id'),
            ]
        ]);
    
        if (is_wp_error($getResponse)) {
            $getResponse = wp_remote_get($url . '?Email=' . $data['Email'], [
                'method' => 'GET',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $secondaryApiKey,
                    'Content-Type' => 'application/json',
                    'PortalId'     => get_option('gravity_api_trap_portal_id'),
                ]
            ]);
        }
    
        if (is_wp_error($getResponse)) {
            error_log('API request failed: ' . $getResponse->get_error_message(), 3, plugin_dir_path(__FILE__) . 'debug.log');
            return;
        }
    
        $existingIndividuals = json_decode(wp_remote_retrieve_body($getResponse), true);
    
        $existingIndividual = null;
        foreach ($existingIndividuals as $individual) {
            if ($individual['properties'][0]['value'] === $data['Email']) {
                $existingIndividual = $individual;
                break;
            }
        }
    
        if ($existingIndividual) {
            // Update existing individual - always as prospect
            $individual = [
                "id" => $existingIndividual['id'],
                "IndividualID" => $existingIndividual['id'], 
                "communities" => [
                    ["NameUnique" => $data['communityunique']]
                ],
                "properties" => [
                    ["property" => "FirstName", "value" => $data['FirstName']], 
                    ["property" => "LastName", "value" => $data['LastName']], 
                    ["property" => "Home Phone", "value" => $data['Phone']],
                    ["property" => "Email", "value" => $data['Email']],
                    ["property" => "type", "value" => $data['primarycontactid']],
                    ["property" => "Status Code", "value" => "Not Yet Classified"],
                    ["property" => "Care Level", "value" => $data['carelevel']],
                    ["property" => "UTM Source", "value" => $data['utmsource']],
                    ["property" => "UTM Medium", "value" => $data['utmmedium']],
                    ["property" => "UTM Campaign", "value" => $data['utmcampaign']],
                    ["property" => "UTM Id", "value" => $data['utmid']],
                    ["property" => "GCLID", "value" => $data['gclid']],
                    ["property" => "FBCLID", "value" => $data['fbclid']],
                    ["property" => "gad", "value" => $data['gad']],
                    ["property" => "?gad", "value" => $data['gad_2']],
                    ["property" => "gad_source", "value" => $data['gad_source']],
                    ["property" => "display", "value" => $data['display']],
                    ["property" => "Market Source", "value" => $data['marketsource']],
                    ["property" => "Lead Status", "value" => "Pending / Not Yet Classified "],
                ],
                "activities" => [
                    [
                        "reInquiry" => true,
                        "description" => "Webform",
                        "activityStatusMasterId" => 3,
                        "activityResultMasterId" => 3,
                        "activityTypeMasterId" => 17
                    ]
                ]
            ];

            $args = [
                'method' => 'POST',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $primaryApiKey,
                    'Content-Type' => 'application/json',
                    'PortalId'     => get_option('gravity_api_trap_portal_id'),
                ],
                'body' => json_encode($individual, JSON_PRETTY_PRINT)
            ];

            error_log('API request JSON data: ' . json_encode($individual, JSON_PRETTY_PRINT), 3, plugin_dir_path(__FILE__) . 'debug.log');

        } else {
            // Create new individual - always as prospect
            $individual = [
                "communities" => [
                    ["NameUnique" => $data['communityunique']]
                ],
                "properties" => [
                    ["property" => "FirstName", "value" => $data['FirstName']], 
                    ["property" => "LastName", "value" => $data['LastName']], 
                    ["property" => "Home Phone", "value" => $data['Phone']],
                    ["property" => "Email", "value" => $data['Email']],
                    ["property" => "type", "value" => $data['primarycontactid']],
                    ["property" => "Status Code", "value" => "Not Yet Classified"],
                    ["property" => "Care Level", "value" => $data['carelevel']],
                    ["property" => "UTM Source", "value" => $data['utmsource']],
                    ["property" => "UTM Medium", "value" => $data['utmmedium']],
                    ["property" => "UTM Campaign", "value" => $data['utmcampaign']],
                    ["property" => "UTM Id", "value" => $data['utmid']],
                    ["property" => "GCLID", "value" => $data['gclid']],
                    ["property" => "FBCLID", "value" => $data['fbclid']],
                    ["property" => "gad", "value" => $data['gad']],
                    ["property" => "?gad", "value" => $data['gad_2']],
                    ["property" => "gad_source", "value" => $data['gad_source']],
                    ["property" => "display", "value" => $data['display']],
                    ["property" => "Market Source", "value" => $data['marketsource']],
                    ["property" => "Request Tour", "value" => $data['requesttour']],
                    ["property" => "Request Brochure", "value" => $data['requestbrochure']],
                    ["property" => "Request Pricing", "value" => $data['requestpricing']],
                    ["property" => "Knowledge is Comfort", "value" => $data['knowledgecomfort']],
                    ["property" => "Seeking Support", "value" => $data['seekingsupport']],
                    ["property" => "Empowering Retirement", "value" => $data['empoweringretirement']],
                    ["property" => "Source Url", "value" => $data['sourceurl']],
					["property" => "DoNotCall", "value" => $data['donotcall']],
					["property" => "DoNotText", "value" => $data['donottext']],
					["property" => "DoNotEmail", "value" => $data['donotemail']],
                    ["property" => "Lead Status", "value" => "Pending / Not Yet Classified "],
                ],
                "activities" => [
                    [
                        "reInquiry" => true,
                        "description" => "Webform",
                        "activityStatusMasterId" => 3,
                        "activityResultMasterId" => 3,
                        "activityTypeMasterId" => 17
                    ]
                ],
                "notes" => [
                    ["Message" => (string)$data['Message']]
                ]
            ];

            $sendData = [
                "individuals" => [
                    $individual
                ]
            ];

            $args = [
                'method' => 'POST',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $primaryApiKey,
                    'Content-Type' => 'application/json',
                    'PortalId'     => get_option('gravity_api_trap_portal_id'),
                ],
                'body' => json_encode($sendData, JSON_PRETTY_PRINT)
            ];

            error_log('API request JSON data: ' . json_encode($sendData, JSON_PRETTY_PRINT), 3, plugin_dir_path(__FILE__) . 'debug.log');
        }
    
        $response = wp_remote_post($url, $args);
    
        if (is_wp_error($response)) {
            $args['headers']['Ocp-Apim-Subscription-Key'] = $secondaryApiKey;
            $response = wp_remote_post($url, $args);
        }
    
        if (is_wp_error($response)) {
            error_log('API request failed: ' . $response->get_error_message(), 3, plugin_dir_path(__FILE__) . 'debug.log');
            return;
        }
    
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
    
        if ($responseCode === 200) {
            error_log('API request successful: ' . $responseCode . ' - ' . $responseBody, 3, plugin_dir_path(__FILE__) . 'debug.log');
        } else {
            error_log('API request failed: ' . $responseCode . ' - ' . $responseBody, 3, plugin_dir_path(__FILE__) . 'debug.log');
        }
    
        return $response;
    }
}
