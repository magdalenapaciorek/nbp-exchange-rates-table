<?php
/*
Plugin Name: Kursy walut NBP
Description: Wtyczka pobiera aktualne kursy walut z NBP API.
Version: 1.0
Author: Magdalena Paciorek
License: GPLv2 or later
Text Domain: nbp_exchange_rates_table
*/


//make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}


if ( !function_exists( 'nbp_exchange_rates_table' ) ) {
	function nbp_exchange_rates_table( $atts ) {

		//check if the the data is already cached
		$current_nbp_rates = get_transient( 'current_nbp_rate' );
		
		//if it's not in the cache retrieve it from NBP API and set the transient for caching purposes
		if ( false === $current_nbp_rates ) {

			$url = 'http://api.nbp.pl/api/exchangerates/tables/a/?format=json';

			//connect to API and retrieve the data
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				
				return;
			}

			//retrieve only body data
			$data = wp_remote_retrieve_body( $response );

			if ( is_wp_error( $data ) ) {

				return;
			}

			set_transient( 'current_nbp_rate', $data, HOUR_IN_SECONDS );
			$current_nbp_rates = get_transient( 'current_nbp_rate' );
		}

		//convert json returned by the API into an array
		$all_currency_data = json_decode( $current_nbp_rates );

		//check if there was a problem and json decoding went wrong
		if ( empty( $all_currency_data ) ) {

			//delete the transient that might be storing an error message
			delete_transient( 'current_nbp_rate' );
			return;
		}

		//set the default currency codes
		$all_currency_codes = 'THB, USD, AUD, HKD, CAD, NZD, SGD, EUR, HUF, CHF, GBP, UAH, JPY, CZK, DKK, ISK, NOK, SEK, HRK, RON, BGN, TRY, ILS, CLP, PHP, MXN, ZAR, BRL, MYR, RUB, IDR, INR, KRW, CNY, XDR';
		
		//get currency codes passed by the user in a shortcode or stay with the default codes
		$atts = shortcode_atts(
			array(
				'currency_codes' => $all_currency_codes,
			), $atts, 'nbp_table' );
		
		//set the array of currency codes that the user wants to display on a page
		$currency_codes = array_map( 'trim', explode( ',', $atts['currency_codes'] ) );
		
		//set an array of all exchange rates passed by the NBP API 
		//this is where currency rates data will come from
		$all_exchange_rates = $all_currency_data[0]->rates;

		//create new array of only these currency objects that were chosen by the user
		$chosen_exchange_rates = [];
		
		//we want to show the curriencies in an order passed in the shortcode 
		//so let's copy chosen exchange rates to the new array in a correct order
		//first let's iterate through the reversed array of codes that come from the shortcode
		foreach ( array_reverse( $currency_codes ) as $reversed_currency_code ) {
	
			//for every currency code let's iterate through an array of all exchange rates
			foreach ( $all_exchange_rates as $key => $currency_code ) {

				//and stop when we find the currency object that
				//matches the currency rate passed in the shortcode
				if ( $reversed_currency_code === $currency_code->code ) {
					
					//let's save the currency object we want to copy
					$currency_to_be_copied = $all_exchange_rates[$key];
					//let's add it to the begining of the new array
					array_unshift( $chosen_exchange_rates, $currency_to_be_copied );
				}
			}
		}

		//set the date for the exchange rates
		$currency_rate_date = date_create_from_format( 'Y-m-d', $all_currency_data[0]->effectiveDate );

		//print the exchange rates chosen by the user in the table
		$table = '<table class="nbp_exchange_rates" style="width:100%">';

			$table .= '<caption>Kursy walut z dnia: ' . date_format( $currency_rate_date, "d/m/Y" ) . '</caption>';

			$table .= '<tr>';
				$table .= '<th>Kod</th>';
				$table .= '<th>Waluta</th>';
				$table .= '<th>Kurs</th>';
			$table .= '</tr>';

			//get the values for individual currencies and put them in a table
			foreach ( $chosen_exchange_rates as $rate ) { 

				$currency_name = $rate->currency;
				$currency_code = $rate->code;
				$currency_rate = $rate->mid;

				$table .= '<tr id="' . esc_attr( strtolower( $currency_code ) ) .'">';
					$table .= '<td>' . esc_html( $currency_code ) . '</td>';
					$table .= '<td>' . esc_html( $currency_name ) . '</td>';
					$table .= '<td>' . round( $currency_rate, 4 ) . ' zł</td>';
				$table .= '</tr>';
			}

		$table .= '</table>';
		
		return $table;
	}
}
//create a shortcode [nbp_table] to display exchange rates table
add_shortcode( 'nbp_table', 'nbp_exchange_rates_table' );

//clear plugin transients on deactivation
function nbp_exchange_rates_table_deactivation() {
    
    delete_transient( 'current_nbp_rate' ); 
}
register_deactivation_hook( __FILE__, 'nbp_exchange_rates_table_deactivation' );