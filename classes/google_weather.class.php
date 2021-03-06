<?php

/**
 * Google Weather API.
 *
 * LICENSE:
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * You may not use this work except in compliance with the License.
 * You may obtain a copy of the License in the LICENSE file, or at:
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * @author       Matthew Gates <info@mgates.me>
 * @copyright    Copyright (c) 2012 Matthew Gates.
 * @license      http://www.apache.org/licenses/LICENSE-2.0
 * @link         https://github.com/Geczy/google-weather-api
 */

namespace Geczy\Weather;
class GoogleWeatherAPI {

	/**
	 * Default settings.
	 */
	public $defaults = array(
		'degree'   => 'f',
		'language' => 'en',
	);

	/**
	 * Location of where to check the weather. Written by setLocation().
	 */
	private $location;

	/**
	 * Google Weather constructor.
	 *
	 * @param     array    $defaults    Override the default options by providing your own.
	 */
	function __construct( $defaults = array() ) {

		$this->defaultSettings($defaults);

	}

	/**
	 *
	 * Default settings.
	 *
	 * @param     array    $defaults    Default options overridden by __construct()
	 */
	private function defaultSettings($defaults) {

		if ( !empty($defaults) ) $this->defaults = array_merge($this->defaults, $defaults);

		$this->setLanguage($this->defaults['language']);
		$this->setDegree($this->defaults['degree']);

	}

	/**
	 * Location of where to check the weather.
	 *
	 * @param    string    $location    This can be either zip, city, coordinates, etc.
	 */
	public function setLocation($location) {

		$this->location = $location;

	}

	/**
	 * Location of where to check the weather.
	 *
	 * @param    string    $language    For example: en, fr, pl, zn-CH.
	 */
	public function setLanguage($language) {

		$this->language = $language;

	}

	/**
	 * Default degree to display weather in.
	 *
	 * @param    string    $degree    Only `f` (fahrenheit) or `c` (celsius) are accepted.
	 */
	public function setDegree($degree = 'f') {

		switch ( $degree ) :

			case 'f':
			case 'c':
				$this->defaults['degree'] = $degree;
				break;

			default :
				$this->defaults['degree'] = 'f';
				break;

		endswitch;

	}

	/**
	 * Process and retrieve weather information.
	 *
	 * Entering a location parameter will override the default location.
	 * Leaving it empty will retrieve the default location.
	 *
	 * @param    string     $location    This can be either zip, city, coordinates, etc.
	 */
	public function getWeather($location) {

		$this->setLocation($location);

		$query = $this->buildRequest();
		$result = $this->sendRequest($query);
		$validated = $this->validateResponse($result);

		/* False if there was an error. */
		if ( empty($validated) ) return false;

		$processed = $this->processResponse($validated);

		return $processed;

	}

	/**
	 * Build the Google HTTP query.
	 *
	 * An HTTP query should follow the format:
	 * http://www.google.com/ig/api?weather=Los%2BAngeles&hl=en&ie=utf-8&oe=utf-8
	 *
	 * @return    string    A complete HTTP query containing location to retrieve.
	 */
	private function buildRequest() {

		$url = 'http://www.google.com/ig/api?';

		$args = array(
			'weather' => trim($this->location),
			'hl'      => $this->language,
			'ie'      => 'utf-8',
			'oe'      => 'utf-8',
		);

		$query = $url . http_build_query($args);

		return $query;

	}

	/**
	 * Load the HTTP query using SimpleXML.
	 *
	 * @param     string    $query    URL to retrieve weather from. See buildRequest().
	 * @return    object    The XML response from Google.
	 */
	private function sendRequest($query) {

		$xml = @simplexml_load_file($query);

		return $xml;

	}

	/**
	 * Check whether the location is valid, and if a response is given.
	 *
	 * @param     object    $response    XML response from Google. See sendRequest().
	 * @return    array     Location info, current weather, and future forecast in the form of an object.
	 */
	private function validateResponse($response) {

		if ( !$response ) return false;

		/* Save the bits that we actually use from the response. */
		$response = array(
			'info'     => $response->xpath("/xml_api_reply/weather/forecast_information"),
			'current'  => $response->xpath("/xml_api_reply/weather/current_conditions"),
			'forecast' => $response->xpath("/xml_api_reply/weather/forecast_conditions")
		);

		/* Remove empty results */
		$response = array_filter( $response );

		return $response;

	}

	/**
	 * Format the response from Google into a nice array.
	 *
	 * See README.md for an example of what this function returns.
	 *
	 * @param     array    $response    XML response, broken into an array of objects. See validateResponse().
	 * @return    array    Weather in a pretty array format.
	 */
	private function processResponse($response) {

		/* City information. */
		$info = array(
			'city' => (string) $response['info'][0]->city['data'],
			'zip'  => (string) $response['info'][0]->postal_code['data'],
			'unit' => (string) $response['info'][0]->unit_system['data'],
		);

		/* Current weather. */
		$current = array(
			'condition'      => (string) $response['current'][0]->condition['data'],
			'temp_f'         => $this->convertDegree((string) $response['current'][0]->temp_f['data']),
			'humidity'       => (string) $response['current'][0]->humidity['data'],
			'icon'           => str_replace('ig/images/weather/', '', (string) $response['current'][0]->icon['data']),
			'wind_condition' => (string) $response['current'][0]->wind_condition['data'],
		);

		/* Future weather. */
		$forecasts = array();
		foreach ( $response['forecast'] as $forecast ) {

			$forecasts[ (string) $forecast->day_of_week['data'] ] = array(
				'low'       => $this->convertDegree((string) $forecast->low['data'], $info['unit']),
				'high'      => $this->convertDegree((string) $forecast->high['data'], $info['unit']),
				'icon'      => str_replace('ig/images/weather/', '', (string) $forecast->icon['data']),
				'condition' => (string) $forecast->condition['data'],
			);

		}

		$weather = array(
			'info'     => $info,
			'current'  => $current,
			'forecast' => $forecasts
		);

		return $weather;

	}

	/**
	 * Convert from Celsius to Fahrenheit or visa versa.
	 *
	 * @param     int       $degree    Temperature in degrees.
	 * @param     string    $degree    Either `US` or `SI`. Used to determine whether $degree is in Celsius or Fahrenheit.
	 * @return    int       Converted degree.
	 */
	private function convertDegree($degree, $unit = 'US') {

		switch ( $this->defaults['degree'] ) :

			case 'c' :
				if ( $unit == 'US' )
					$degree = round((5/9) * ($degree - 32));
				break;

			case 'f' :
				if ( $unit != 'US' )
					$degree = round($degree * 9/5 + 32);
				break;

		endswitch;

		return $degree;

	}

}