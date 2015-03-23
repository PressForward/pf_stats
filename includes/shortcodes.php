<?php

class PF_Stats_Shortcodes {

	public static function init() {

		static $instance;
		if ( ! is_a( $instance, 'PF_Stats_Shortcodes' ) ) {
			$instance = new self();
		}
		return $instance;

	}

	private function __construct() {

		add_shortcode( 'pf_wordcount_last_thirty', array( $this, 'pf_wordcount_last_thirty' ) );
		add_shortcode( 'pf_author_leaderboard', array( $this, 'author_leaderboard' ) );

	}

	// Add Shortcode
	public function pf_wordcount_last_thirty() {
			$wc = 0;
			$c = 0;
			$the_query = new WP_Query(
				array(
					'nopaging' => true,
					'meta_key' => pressforward_stats()->meta_key,
					'date_query' => array(
						'column' => 'post_date_gmt',
						'after'  => '30 days ago',
					)

				)
			);
		if ( $the_query->have_posts() ) :

				while ( $the_query->have_posts() ) : $the_query->the_post();
					$content = get_post_field( 'post_content', get_the_ID() );
					$word_count = str_word_count( strip_tags( $content ) );
					$wc = $wc+$word_count;
					$c++;
				endwhile;

			wp_reset_postdata();

			$s = $this->the_shortcode( 'pf_wordcount_last_thirty', array( 'word_count' => $wc, 'count' => $c, 'days' => '30' ) );

		else :

			$s = $this->the_shortcode( 'read_nothing', array( 'days' => '30' ) );

		endif;

		return $s;
	}

	public function author_leaderboard(){
		$c = 0;
		$the_query = new WP_Query(
			array(
				'nopaging' => true,
				'posts_per_page' => -1,
				'meta_key' => pressforward_stats()->meta_author_key,
			)
		);

		$authors = array();

		if ( $the_query->have_posts() ) :

			while ( $the_query->have_posts() ) : $the_query->the_post();
				$authors = $this->set_author_into_leaderboard( get_the_ID(), $authors );
				$c++;
			endwhile;

			wp_reset_postdata();

			$s = $this->the_shortcode( 'pf_author_leaderboard', array( 'authors' => $authors ) );

		else :

			$s = $this->the_shortcode( 'read_nothing', array( 'days' => '30' ) );

		endif;

		return $s;

	}

	private function cmp_authors($a, $b){
		return $b['count'] - $a['count'];
	}

	private function get_author_leaderboard($authors){
		uasort($authors, array( $this, 'cmp_authors' ) );
		$total = 0;
		$count = 0;
		$singles = 0;
		$male = 0;
		$female = 0;
		$unknown = 0;
		$more_than_two = 0;
		$leaderboard = '<ul>';
		foreach ( $authors as $author ){
			$total = $total+$author['count'];
			if ($author['count'] < 2){
				$singles++;
			}
			if ($author['count'] > 2){
				$more_than_two++;
			}
			if ( !empty( $author['gender'] ) ){
				if ( 'MALE' == $author['gender'] ){
					$male++;
				}
				if ( 'FEMALE' == $author['gender'] ){
					$female++;
				}
				if ( 'UNKNOWN' == $author['gender'] ) {
					$unknown++;
				}
			}
			$leaderboard .= $this->add_author_leaderboard_entry($author);
			$count++;
		}
		$leaderboard .= '</ul>';
		$more_than_one = $count - $singles;
		$leaderboard = "<p>$count authors over $total articles. $singles authors archived only once. $more_than_one authors archived more than once. $more_than_two authors archived more than twice.</p>\n
			<p>$female authors are probably female. $male authors are probably male. $unknown number of authors can't have their gender algorithmically determined.</p>
			\n" . $leaderboard;
		return $leaderboard;
	}

	private function set_author_into_leaderboard( $id, $authors ){
		$author = pf_get_post_meta( $id, pressforward_stats()->meta_author_key );
		$author_slug = str_replace(' ', '_', strtolower($author) );
		if ( !empty( $authors[$author_slug] ) ) {
			$authors = $this->set_author_count( $author_slug, $authors );
		} else {
			$authors = $this->set_new_author_object($author_slug, $author, $authors );
		}

		return $authors;
	}

	private function set_author_count( $author_slug, $authors ){
		$authors[$author_slug]['count'] = $authors[$author_slug]['count']+1;
		return $authors;
	}

	private function set_author_gender( $name ) {
		$author_name = (string) $name;
		$author_first_name_array = explode( ' ', $author_name );
		$author_first_name = (string) $author_first_name_array[0];
		if ( empty($author_first_name) ) {
			if ( empty( $author_name ) ){
				$author_first_name = "No author found.";
			} else {
				$author_first_name = $name;
			}
		}
		//var_dump($author_first_name . ': ');
		$gender = pressforward_stats()->gender_checker->test($author_first_name);
		return $gender;
	}

	private function set_author_gender_confidence(){
		//var_dump($gender . "\n");
		$confidence = pressforward_stats()->gender_checker->getPreviousMatchConfidence();
		$confidence = (string) $confidence;
		return $confidence;

	}

	private function set_new_author_object( $author_slug, $author, $authors ){
		$authors[$author_slug] = array( 
										'count' 			=> 1,
										'name'				=> $author,
										'gender'			=> $this->set_author_gender($author),
										'gender_confidence'	=> $this->set_author_gender_confidence()
									);



		return $authors;
	}

	private function add_author_leaderboard_entry($author){
		if ( empty($author) ) {
			$author = array();
			$author['count'] = 0;
		}
		if ( ( empty($author['name']) ) ) {
			$author['name'] = 'No author found.';
		}
		$s = "\n<li>";
		$s .= $author['name'] . ' (' . $author['count'] . ')';
		#var_dump(pressforward_stats()->gender_checker->test($author['name']) ); var_dump( pressforward_stats()->gender_checker->getPreviousMatchConfidence() ); die();

		$s .= ' This author is likely '. $author['gender'] . '. Confidence: ' . $author['gender_confidence'];
		$s .= '</li>';
		return $s;
	}

	public function the_shortcode($code, $args){
		$s = '';
		switch ( $code ) {
			case 'pf_wordcount_last_thirty':
				$s = sprintf( __("I've read %s words across %s posts in the past %s days.", 'pf' ), $args['word_count'], $args['count'], $args['days'] );
				break;
			case 'pf_author_leaderboard':
				$s = $this->get_author_leaderboard($args['authors']);
				return $s;
				break;
			case 'not_30':
				$s = sprintf( __( 'I\'ve read nothing in the past %s days.', 'pf' ), $args['days'] );
				break;
		}

		return '<p>' . $s  . '</p>';

	}

}