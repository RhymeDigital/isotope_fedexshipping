<?php

/**
 * FedEx Integration for Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2015 Rhyme Digital, LLC.
 *
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace FedExAPI;

use FedExAPI\FedExAPI;

/**
 * Handles the sending, receiving, and processing of tracking data
 * 
 * @author James I. Armes <jamesiarmes@gmail.com>
 * @package php_ups_api
 */
class FedExAPITracking extends FedExAPI {
	/**
	 * Node name for the root node
	 * 
	 * @var string
	 */
	const NODE_NAME_ROOT_NODE = '';
	
	/**
	 * Array of inquiry data
	 * 
	 * @access protected
	 * @var array
	 */
	protected $inquiry_array;
	
	/**
	 * Tracking number that we are requesting data about
	 * 
	 * @access protected
	 * @var string
	 */
	protected $tracking_number;
	
	/**
	 * Constructor for the Object
	 * 
	 * @access public
	 * @param string $tracking_number number of the pacaage(s) we are tracking
	 * @param array $inquiry array of inquiry data
	 */
	public function __construct($tracking_number = null, $inquiry = array()) {
		parent::__construct();
		
		// set object properties
		$this->server .= 'Track';
		$this->tracking_number = $tracking_number;
		$this->inquiry_array = $inquiry;
	} // end function __construct()
	
	/**
	 * Gets the current tracking number for the object
	 * 
	 * @access public
	 * @return string the current tracking number
	 */
	public function getTrackingNumber() {
		return $this->tracking_number;
	} // end function getTrackingNumber()
	
	/**
	 * Sets a new tracking number for the object
	 * 
	 * @access public
	 * @param string $value numeric tracking number
	 */
	public function setTrackingNumber($value) {
		$this->tracking_number = $value;
			
		return true;
	} // sets a new tracking number
	
	/**
	 * Builds the XML used to make the request
	 * 
	 * If $customer_context is an array it should be in the format:
	 * $customer_context = array('Element' => 'Value');
	 * 
	 * @access public
	 * @param array|string $cutomer_context customer data
	 * @return string $return_value request XML
	 */
	public function buildRequest($customer_context = null) {
		// create the new dom document
		$xml = new \DOMDocument('1.0','UTF-8');
		
		$track = $xml->appendChild($xml->createElementNS("http://fedex.com/ws/track/v2",'ns:TrackRequest'));
		$track->setAttributeNode(new \DOMAttr('xmlns:xsi',"http://www.w3.org/2001/XMLSchema-instance"));

		$auth = $track->appendChild($xml->createElement('ns:WebAuthenticationDetail'));

		$credentials = $auth->appendChild($xml->createElement('ns:UserCredential'));
		$credentials->appendChild($xml->createElement('ns:Key',$this->access_key));
		$credentials->appendChild($xml->createElement('ns:Password',$this->password));
		
		$details = $track->appendChild($xml->createElement('ns:ClientDetail'));
		$details->appendChild($xml->createElement('ns:AccountNumber',$this->accountNumber));
		$details->appendChild($xml->createElement('ns:MeterNumber',$this->meterNumber));	
	
		$details2 = $track->appendChild($xml->createElement('ns:TransactionDetail'));
		$details2->appendChild($xml->createElement('ns:CustomerTransactionId','EDIT_LATER'));
	
		$version = $track->appendChild($xml->createElement('ns:Version'));
		$version->appendChild($xml->createElement('ns:ServiceId','trck'));
		$version->appendChild($xml->createElement('ns:Major',2));
		$version->appendChild($xml->createElement('ns:Intermediate',0));
		$version->appendChild($xml->createElement('ns:Minor',0));

		$trck = $track->appendChild($xml->createElement('ns:PackageIdentifier'));	
		$trck->appendChild($xml->createElement('ns:Value',$this->tracking_number));
		$trck->appendChild($xml->createElement('ns:Type','TRACKING_NUMBER_OR_DOORTAG'));
		
		$inc = $track->appendChild($xml->createElement('ns:IncludeDetailedScans', true));

		
		return $xml->saveXML();
	} // end function buildRequest()
	
	/**
	 * Gets the number of packages related to the tracking number
	 * 
	 * @access public
	 * @return integer $return_value number of packages for this tracking number
	 */
	public function getNumberOfPackages() {
		$return_value = count(
			$this->response_array['Shipment']['Package']['Activity']);
		
		return $return_value;
	} // end function getNumberOfPackages()
	
	/**
	 * Gets the status of all packages
	 * 
	 * @access public
	 * @return array $return_value status of each package
	 */
	public function getPackageStatus() {
		$return_value = array();
		
		// iterate over the packages and create a status array for each
		$packages = $this->response_array['Shipment']['Package']['Activity'];
		foreach ($packages as $key => $current_package) {
			$status_type = $current_package['Status']['StatusType'];
			$return_value[$key] = array(
				'code' => $status_type['Code'],
				'description' => $status_type['Description'],
			); // end $return_value[$key]
		} // end for each package
		
		return $return_value;
	} // end function getPackageStatus()
	
	/**
	 * Gets the shipping address of the package(s)
	 * 
	 * @return array $return_value array of address information
	 */
	public function getShippingAddress() {
		$return_value = array();
		
		// get the address and iterate over its parts
		$address = $this->response_array['Shipment']['ShipTo']['Address'];
		foreach($address as $key => $address_part) {
			// check which address part this is
			switch ($key) {
				case 'AddressLine1':
					
					$return_value['address1'] = $address_part;
					break;
					
				case 'AddressLine2':
					
					$return_value['address2'] = $address_part;
					break;
					
				case 'City':
					
					$return_value['city'] = $address_part;
					break;
					
				case 'StateProvinceCode':
					
					$return_value['state'] = $address_part;
					break;
					
				case 'PostalCode':
					
					$return_value['zip_code'] = $address_part;
					break;
					
				case 'CountryCode':
					
					$return_value['country'] = $address_part;
					break;
					
				default:
					
					$return_value[$key] = $address_part;
					break;
					
			} // end switch ($key)
		} // end for each address part
		
		return $return_value;
	} // end function getShippingAddress()
	
	/**
	 * Gets the method used to ship the package(s)
	 * 
	 * @return array $return_value array of information about the shipping
	 * method
	 */
	public function getShippingMethod() {
		$service = $this->response_array['Shipment']['Service'];
		
		// create the array of shipping information
		$return_value = array(
			'code' => $service['Code'],
			'description' => $service['Description'],
		); // end $return_value
		
		return $return_value;
	} // end function getShippingMenthod()
	
	/**
	 * Builds the element required for an inquery
	 * 
	 * @param DOMElement $dom_element
	 * @return DOMElement
	 */
	protected function buildRequest_Inquiry(&$dom_element) {
		// build the element
		$reference_number = $dom_element->appendChild(
			new \DOMElement('ReferenceNumber'));
		$reference_number->appendChild(
			new \DOMElement('Value',
				$this->inquiry_array['reference_number']));
		$dom_element->appendChild(
			new \DOMElement('ShipperNumber',
				$this->inquiry_array['shipper_number']));
		
		return $reference_number;
	} // end function buildRequest_Inquiry()
	
	/**
	 * Returns the name of the servies response root node
	 * 
	 * @access protected
	 * @return string
	 * 
	 * @todo remove after phps self scope has been fixed
	 */
	protected function getRootNodeName() {
		return self::NODE_NAME_ROOT_NODE;
	} // end function getRootNodeName()
} // end class UpsAPI_Tracking
