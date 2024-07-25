<?php

/**
 * -----------------------------------------------------------------------------
 * Plugin Name: ClassicPress Integrity Checker
 * Description: Check core files against MD5.
 * Version: 0.0.1
 * Requires PHP: 7.4
 * Requires CP: 1.0
 * Author: Simone Fioravanti
 * Author URI: https://software.gieffeedizioni.it
 * Plugin URI: https://software.gieffeedizioni.it
 * Text Domain: cp-integrity-checker
 * Domain Path: /languages
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 */

namespace XXSimoXX\CPIntegrityChecker;

class CPIntegrityChecker {

	private $screen  = '';
	const SLUG       = 'cp-integrity-checker';

	public function __construct() {
		add_action('admin_menu', [$this, 'create_settings_menu'], 100);
	}

	public function create_settings_menu() {
		$this->screen = add_menu_page(
			esc_html__('ClassicPress Integrity Checker', 'cp-integrity-checker'),
			esc_html__('Integrity Checker', 'cp-integrity-checker'),
			'manage_options',
			self::SLUG,
			[$this, 'render_menu'],
			'dashicons-shield'
		);
	}

	private function check($version, $url) {
		$response = wp_remote_get($url.$version.'.json');
		if (is_wp_error($response)) {
			return false;
		}
		$error = wp_remote_retrieve_response_code($response);
		if ($error !== 200) {
			return false;
		}
		$response = wp_remote_retrieve_body($response);
		$data = json_decode($response, true);
		if ($data === null) {
			return false;
		}
		if (!isset($data['checksums'])) {
			return false;
		}

		$fail = [];
		foreach ($data['checksums'] as $path => $md5) {
			if (!file_exists(ABSPATH.$path) || !is_readable(ABSPATH.$path)) {
				continue;
			}
			$checksum = md5_file(ABSPATH.$path);
			if ($checksum === $md5) {
				continue;
			}
			$fail[] = $path;
		}
		return $fail;
	}

	public function render_menu () {
		$url     = apply_filters('cpic-url', 'https://api-v1.classicpress.net/v1/checksums/md5/');
		$version = apply_filters('cpic-version', classicpress_version());

		echo '<div class="wrap">';
		echo '<div class="cpic cpic-general">';
		echo '<h1>'.esc_html__('ClassicPress Integrity Checker', 'cp-integrity-checker').'</h1>';
		echo '<p>'.esc_html__('Check core files against MD5.', 'cp-integrity-checker').'<p>';
		echo '<p>'.sprintf(esc_html__('Using signatures from %s.', 'cp-integrity-checker'), esc_html($url.$version.'.json')).'</p>';
		echo '<div class="cpic cpic-results">';

		$results = $this->check($version, $url);
		if ($results === false) {
			esc_html_e('Failed to get checksums.', 'cp-integrity-checker');
		} else {
			echo '<h2>'.sprintf(esc_html__('Found %d mismatch.', 'cp-integrity-checker'), count($results)).'</h2>';
			echo '<ol>';
			foreach ($results as $failed) {
				echo '<li>'.esc_html($failed).'</li>';
			}
			echo '</ol>';
		}

		echo '</div></div>';
	}
}

new CPIntegrityChecker;
