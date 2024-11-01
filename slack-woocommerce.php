<?php
/**
 * Plugin Name: Slack WooCommerce
 * Plugin URI: http://gedex.web.id/wp-slack/
 * Description: This plugin allows you to send notifications to Slack channels whenever payment in WooCommerce is marked as complete.
 * Version: 0.2.0
 * Author: Akeda Bagus
 * Author URI: http://gedex.web.id
 * Text Domain: slack-woocommerce
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 4.4
 * Tested up to: 4.7
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package Slack_WooCommerce
 */

/**
 * Adds new event that send notification to Slack channel
 * when a payment is marked as complete.
 *
 * @param  array $events List of events.
 * @return array
 *
 * @filter slack_get_events
 */
function wp_slack_woocommerce_order_status_completed( $events ) {
	$events['woocommerce_order_status_completed'] = array(

		// Action in WooCommerce to hook in to get the message.
		'action' => 'woocommerce_order_status_completed',

		// Description appears in integration setting.
		'description' => __( 'When a payment in WooCommerce is marked as complete', 'slack-woocommerce' ),

		// Message to deliver to channel. Returns false will prevent
		// notification delivery.
		'message' => function( $order_id ) {
			$order = wc_get_order( $order_id );

			$date = is_callable( array( $order, 'get_date_completed' ) )
				? $order->get_date_completed()
				: $order->completed_date;
			$url  = add_query_arg(
				array(
					'post'   => $order_id,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);

			$user_id = is_callable( array( $order, 'get_user_id' ) )
				? $order->get_user_id()
				: $order->user_id;

			if ( $user_id ) {
				$user_info = get_userdata( $user_id );
			}

			if ( ! empty( $user_info ) ) {
				if ( $user_info->first_name || $user_info->last_name ) {
					$username = esc_html( ucfirst( $user_info->first_name ) . ' ' . ucfirst( $user_info->last_name ) );
				} else {
					$username = esc_html( ucfirst( $user_info->display_name ) );
				}
			} else {
				$billing_first_name = is_callable( array( $order, 'get_billing_first_name' ) )
					? $order->get_billing_first_name()
					: $order->billing_first_name;
				$billing_last_name = is_callable( array( $order, 'get_billing_last_name' ) )
					? $order->get_billing_last_name()
					: $order->billing_last_name;

				if ( $billing_first_name || $billing_last_name ) {
					$username = trim( $billing_first_name . ' ' . $billing_last_name );
				} else {
					$username = __( 'Guest', 'slack-woocommerce' );
				}
			}

			// Remove HTML tags generated by WooCommerce.
			add_filter( 'woocommerce_get_formatted_order_total', 'wp_strip_all_tags', 10, 1 );
			$total = html_entity_decode( $order->get_formatted_order_total() );
			remove_filter( 'woocommerce_get_formatted_order_total', 'wp_strip_all_tags', 10 );

			// Returns the message to be delivered to Slack.
			return apply_filters( 'slack_woocommerce_order_status_completed_message',
				sprintf(
					__( 'New payment with amount *%1$s* has been made by *%2$s* on *%3$s*. <%4$s|See detail>', 'slack-woocommerce' ),
					$total,
					$username,
					$date,
					$url
				),
				$order
			);
		},
	);

	return $events;
}
add_filter( 'slack_get_events', 'wp_slack_woocommerce_order_status_completed' );