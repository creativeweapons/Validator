<?php

/**
 * Validator
 *
 * Validates passed values
 *
 * @author Simón Urzúa <simon@productor.cl>
 */

namespace CreativeWeapons\MiniFramework;

class Validator
{

  /**
	 * Known validation types
	 * @var array
	 */
	private $_validation_types = array(
		'required', 'email', 'min', 'max', 'exact', 'is', 'ip', 'url', 'phone', 'zip', 'date'
	);

	/**
	 * Allowed protocols for URL validation
	 * @var array
	 */
	private $_allowed_url_protocols = array(
		'http', 'https'
	);

	/**
	 * Validations to apply
	 * @var array
	 */
	private $_validations = array();

	/**
	 * Errors in validation
	 * @var array
	 */
	private $_exceptions = array();

	// Constructor

	public function __construct()
	{
		foreach($this->_validation_types as $validation_type){
			if (!method_exists($this, 'validate_'.$validation_type)) {
				throw new Exception("Can't find '{$validation_type}' validation type");
			}
		}
	}

	 // Getters & Setters

	/**
	 * Add a new validation to apply
	 *
	 * @param string $field Field name to validate
	 * @param string $type Type of validation
	 * @param string $string String to validate
	 * @throws Exception
	 */
	public function addValidation($field, $type, $string, array $params = array())
	{
		if(in_array($type, $this->_validation_types)){
			$this->_validations[$field][$type] = array(
				'string' => $string,
				'params' => $params);
		}
		else {
			throw new Exception("Unknown '{$type}' validation type.");
		}
	}

	/**
	 * Replace $_allowed_url_protocols for URL validation
	 *
	 * @param array $protocols
	 */
	public function setAllowedUrlProtocols(array $protocols)
	{
		$this->_allowed_url_protocols = $protocols;
	}

	/**
	 * Add protocol to $_allowed_url_protocols for URL Validation
	 *
	 * @param string $protocol
	 */
	public function addAllowedUrlProtocols($protocol)
	{
		$this->_allowed_url_protocols[] = $protocol;
	}

	/**
	 * Returns a list of validations
	 *
	 * @return array
	 */
	public function listValidations()
	{
		return $this->_validation_types;
	}

	/**
	 * Add an error in validation
	 *
	 * @param string $field
	 * @param string $message
	 */
	private function addException($field, $message)
	{
		$this->_exceptions[] = array('field' => $field, 'message' => $message);
	}

	/**
	 * Returns all exceptions
	 * @return array:
	 */
	public function getExceptions()
	{
		return $this->_exceptions;
	}

	 // Methods

	/**
	 * Parse an a validation rules Array
	 * @param array $array
	 */
	public function parse(array $array)
	{
		foreach($array as $item){
			$validations = explode('|', $item[2]);
			foreach($validations as $validation){
				$open = explode('[', trim($validation,']'));
				$parameters = array();
				if(count($open) > 1){
					$params = explode(',', $open[1]);
					foreach($params as $param){
						$param = explode('=', $param);
						switch($open[0]){
							case 'min':   $key = 'min';   $value = $param[0]; break;
							case 'max':   $key = 'max';   $value = $param[0]; break;
							case 'exact': $key = 'exact'; $value = $param[0]; break;
							case 'is':    $key = 'type';  $value = $param[0]; break;
							default:
								$key = trim($param[0]);
								$value = (isset($param[1]))? trim($param[1]) : true;
						}
						$parameters[$key] = $value;
					}
				}
				$this->addValidation(trim($item[0]), trim($open[0]), trim($item[1]), $parameters);
			}
		}
	}

	/**
	 * Run the validations
	 *
	 * @throws Exception
	 */
	public function run()
	{
		if(empty($this->_validations)){
			throw new Exception('No validations to apply');
		}
		else {
			foreach($this->_validations as $field => $key){
				foreach($key as $type => $validation){
					if(isset($this->_validations[$field]['required']) && ($validation['string'] == '' || !isset($validation['string']) || is_null($validation['string']))){
						$this->addException($field, 'Required and not empty');
						break;
					}
					elseif(!isset($this->_validations[$field]['required']) && ($validation['string'] == '' || !isset($validation['string']) || is_null($validation['string']))){
						break;
					}
					$callback = array($this, 'validate_'.$type);
					$params = array($field, $validation['string'], $validation['params']);
					call_user_func_array($callback, $params);
				}
			}
			if(empty($this->_exceptions)){
				return true;
			}
			return false;
		}
	}

	private function validate_required($field, $string, array $params = array())
	{
		if(!isset($string) || is_null($string) || $string == ''){
			$this->addException($field, 'Required and not empty');
			return false;
		}
		return true;
	}

	/**
	 * Validates email field
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['no_hostname_check']
	 * @return boolean
	 */
	private function validate_email($field, $string, array $params)
	{
		$return = true;

		if(!filter_var($string, FILTER_VALIDATE_EMAIL)){
			$return = false;
			$this->addException($field, 'Email bad formatted');
		}

		if(!(isset($params['no_hostname_check']))){
			$domain = explode('@', $string);
			if(isset($domain[1])){
				if(!checkdnsrr($domain[1],"MX")){
					$return = false;
					$this->addException($field, "Email hostname don't exist");
				}
			}
		}
		return $return;
	}

	/**
	 * Min Lenght validation
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['min']
	 */
	private function validate_min($field, $string, array $params)
	{
		if(!isset($params['min'])){
			$this->addException($field, 'Min Lenght validation requires $params[\'min\']');
			return false;
		}
		if(strlen($string) < (int)$params['min']){
			$this->addException($field, 'Too short');
			return false;
		}
		return true;
	}

	/**
	 * Max Lenght Validation
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['max']
	 */
	private function validate_max($field, $string, array $params)
	{
		if(!isset($params['max'])){
			$this->addException($field, 'Max Lenght validation requires $params[\'max\']');
			return false;
		}
		if(strlen($string) > (int)$params['max']){
			$this->addException($field, 'Too large');
			return false;
		}
		return true;
	}

	/**
	 * Exact Lenght Validation
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['exact']
	 */
	private function validate_exact($field, $string, array $params)
	{
		if(!isset($params['exact'])){
			$this->addException($field, 'Exact Lenght validation requires $params[\'exact\']');
			return false;
		}
		if(strlen($string) <> (int)$params['exact']){
			$this->addException($field, 'Will be '.$params['exact']. ' lenght');
			return false;
		}
		return true;
	}

	/**
	 * Is Validation
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['type']
	 */
	private function validate_is($field, $string, array $params)
	{
		if(!isset($params['type'])){
			$this->addException($field, 'Is validation requires $params[\'type\']');
			return false;
		}
		switch($params['type']){
			case 'int':
				if((int)$string === 0 && $string === '0'){
					return true;
				}
				elseif((int)$string <> 0){
					return true;
				}
				else {
					$this->addException($field, 'Not integer');
					return false;
				}
				break;
			case 'bool':
				if(!($string === true || $string === false)){
					$this->addException($field, 'Not boolean');
					return false;
				}
				break;
			case 'null':
				if($string != 'null'){
					$this->addException($field, 'Not null');
					return false;
				}
				break;
			default:
				$this->addException($field, 'Type not found');
				break;

		}
		return true;
	}

	/**
	 * Ip Validation
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['v4']
	 * @param array $params['v6']
	 * @param array $params['reject_private']
	 */
	private function validate_ip($field, $string, array $params)
	{
		$return = true;

		if(isset($params['v4'])){
			if(!filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
				$return = false;
				$this->addException($field, 'IpV4 not valid');
			}
		}
		if(isset($params['v6'])){
			if(!filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
				$return = false;
				$this->addException($field, 'IpV6 not valid');
			}
		}
		if(isset($params['reject_private'])){
			if(!filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)){
				$return = false;
				$this->addException($field, 'Private ip not allowed');
			}
			if(!filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)){
				$return = false;
				$this->addException($field, 'Reserved ip not allowed');
			}
		}
		if(!isset($params['v4']) && !isset($params['v6']) && !isset($params['reject_private'])){
			if(!filter_var($string, FILTER_VALIDATE_IP)){
				$return = false;
				$this->addException($field, 'Ip not valid');
			}
		}
		return $return;
	}

	/**
	 * Url Validation
	 *
	 * @param string $field
	 * @param string $string
	 */
	private function validate_url($field, $string, array $params)
	{
		$return = true;

		if(!filter_var($string, FILTER_VALIDATE_URL)){
			$this->addException($field, 'Url not valid');
			$return = false;
		}
		else {
			$protocol = explode('://', $string);

			if(count($protocol) != 2){
				$this->addException($field, 'Protocol repeated');
				$return = false;
			}

			if(!in_array($protocol[0], $this->_allowed_url_protocols)){
				$this->addException($field, 'Protocol not allowed');
				$return = false;
			}

		}
		return $return;
	}

	/**
	 * Validates Mexican Phone & Cell
	 *
	 * @param string $field
	 * @param string $string
	 */
	private function validate_phone($field, $string, array $params)
	{
		$this->validate_is($field, $string, array('type' => 'int'));
		$this->validate_exact($field, $string, array('exact' => 10));
	}

	/**
	 * Validates Mexican ZIP Code
	 *
	 * @param string $field
	 * @param string $string
	 */
	private function validate_zip($field, $string, array $params)
	{
		$this->validate_is($field, $string, array('type' => 'int'));
		$this->validate_exact($field, $string, array('exact' => 5));
	}

	/**
	 * Validates Date
	 *
	 * @param string $field
	 * @param string $string
	 * @param array $params['format'] Default: d/m/Y
	 */
	private function validate_date($field, $string, array $params)
	{
		if(!isset($params['format'])){
			$params['format'] = "d/m/Y";
		}
		$date = date_parse_from_format($params['format'], $string);

		if(!checkdate($date['month'], $date['day'], $date['year'])){
			$this->addException($field, 'Date not valid');
			return false;
		}
		return true;

	}
}