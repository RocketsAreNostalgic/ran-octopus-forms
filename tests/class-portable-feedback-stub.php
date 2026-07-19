<?php
/**
 * Jetpack Feedback contract used by portable integration tests.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace Automattic\Jetpack\Forms\ContactForm;

if ( ! class_exists( Feedback::class ) ) {
	/**
	 * Minimal Jetpack 15.5 Feedback contract used by integration tests.
	 */
	final class Feedback {
		/**
		 * Authoritative form IDs keyed by feedback post ID.
		 *
		 * @var array<int,int>
		 */
		public static $form_ids = array();

		/**
		 * Feedback post ID.
		 *
		 * @var int
		 */
		private $post_id;

		/**
		 * @param int $post_id Feedback post ID.
		 */
		private function __construct( $post_id ) {
			$this->post_id = $post_id;
		}

		/**
		 * @param int $post_id Feedback post ID.
		 * @return self
		 */
		public static function get( $post_id ) {
			return new self( (int) $post_id );
		}

		/**
		 * @return int
		 */
		public function get_form_id() {
			return (int) ( self::$form_ids[ $this->post_id ] ?? 0 );
		}
	}
}
