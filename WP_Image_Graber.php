<?php
/*
Plugin Name: WP Image Graber
Plugin URI: https://github.com/angelorocha/wp-image-graber
Description: Import post remote images and add thumbnails automatically
Version: 1.0.0
Author: Angelo Rocha
Author URI: https://angelorocha.com.br
Domain Path: /lang
Text Domain: wig
*/

defined( 'ABSPATH' ) or die( 'Oooooh Boy...' );

class WP_Image_Graber {

	private $version = '20180829';
	private $post_per_page = 10;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'wig_admin_page' ) );
		add_action( 'admin_enqueue_scripts', [ $this, 'wig_enqueue_style' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'wig_enqueue_script' ] );
		add_action( 'publish_post', [ $this, 'wig_set_thumbnail' ] );
		//add_action( 'wp_head', [ $this, 'wig_set_thumbnail' ] ); # for test...
	}

	/**** Import Images ****/

	public function wig_set_thumbnail( $post_id ) {
		if ( ! has_post_thumbnail() ) {
			global $wpdb;
			$content    = get_post( $post_id )->post_content;
			$thumbnail  = [];
			$image_name = [];
			$image_url  = [];
			$image_ext  = [];
			$upload_dir = wp_upload_dir();
			$i          = 0;

			$html = new DOMDocument();
			@$html->loadHTML( $content );

			$image = $html->getElementsByTagName( 'img' );

			$have_image = count( $image ) > 0 ? true : false;

			/**** If is have image ****/
			if ( $have_image ) {
				foreach ( $image as $src ) {
					$remote = parse_url( $src->getAttribute( 'src' ) )['host'];
					$local  = parse_url( get_site_url() )['host'];
					if ( $remote !== $local ) {

						$thumbnail[] = $src->getAttribute( 'src' );

						if ( ! empty( pathinfo( $thumbnail[ $i ] )['extension'] ) ) {
							#$image_ext[]  = pathinfo( basename( preg_replace( '/(?<=jpg|png|gif|jpeg).*/', '', $thumbnail[ $i ] ) ) )['extension'];
							$temp_ext[]   = explode( '?', pathinfo( $thumbnail[ $i ] )['extension'] );
							$image_ext[]  = count( $temp_ext[ $i ] ) > 1 ? $temp_ext[ $i ][0] : pathinfo( $thumbnail[ $i ] )['extension'];
							$image_name[] = md5( basename( $src->getAttribute( 'src' ) ) ) . '.' . $image_ext[ $i ];
						} else {
							$image_name[] = md5( basename( $src->getAttribute( 'src' ) ) ) . '.jpg';
						}

						$image_url[]  = $upload_dir['url'] . '/' . $image_name[ $i ];
						$image_pach[] = $upload_dir['path'] . '/' . $image_name[ $i ];


						/**** Attach image to post ****/
						$ch = curl_init( $thumbnail[ $i ] );
						$fp = fopen( $upload_dir['path'] . '/' . $image_name[ $i ], 'wb' );
						curl_setopt( $ch, CURLOPT_FILE, $fp );
						curl_setopt( $ch, CURLOPT_HEADER, 0 );
						curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
						curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)' );
						curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
						curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
						curl_setopt( $ch, CURLOPT_TIMEOUT, 900 );
						curl_exec( $ch );
						curl_close( $ch );
						fclose( $fp );

						$parent_post_id = $post_id;
						$filetype       = wp_check_filetype( $thumbnail[ $i ], null );
						$attachment     = array(
							'guid'           => $upload_dir['url'] . '/' . $image_name[ $i ],
							'post_mime_type' => $filetype['type'],
							'post_title'     => 'auto_thumbnail_' . $image_name[ $i ],
							'post_content'   => '',
							'post_status'    => 'inherit'
						);
						$attach_id      = wp_insert_attachment( $attachment, $image_pach[ $i ], $parent_post_id );
						require_once( ABSPATH . 'wp-admin/includes/image.php' );
						$attach_data = wp_generate_attachment_metadata( $attach_id, $image_pach[ $i ] );
						wp_update_attachment_metadata( $attach_id, $attach_data );
						/**** Select first image for thumbnail ****/
						if ( $i < 1 ) {
							set_post_thumbnail( $parent_post_id, $attach_id );
						}
						/**** End image attach ****/
					}
					$i ++;
				}
				$i = 0;
			}
			$wpdb->update( $wpdb->posts, array( 'post_content' => str_ireplace( $thumbnail, $image_url, $content ) ), array( 'ID' => $post_id ) );
		}
	}

	/**
	 * Enqueue plugin styles
	 */
	public function wig_enqueue_style() {
		wp_enqueue_style( 'wig-style', plugin_dir_url( __FILE__ ) . 'css/style.css', array(), $this->version, false );
	}

	/**
	 * Enqueue plugin scripts
	 */
	public function wig_enqueue_script() {
		wp_enqueue_script( 'wig-script', plugin_dir_url( __FILE__ ) . 'js/js.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * @param $string
	 * @param int $size
	 *
	 * @return string
	 *
	 * Define size for titles
	 */
	public function wig_string_length( $string, $size = 40 ) {

		return strlen( $string ) > $size ? substr( $string, 0, 40 ) . '...' : $string;
	}

	/**
	 * Plugin internationalization
	 */
	public function wig_plugin_text_domain() {
		load_plugin_textdomain( 'wig', false, basename( dirname( __FILE__ ) ) . '/lang/' );
	}

	/**
	 * Plugin admin pages
	 */
	public function wig_admin_page() {
		add_menu_page(
			'WP Image Graber',
			'WP Image Graber',
			'administrator',
			'wig-menu-page',
			[ $this, 'wig_admin_content' ],
			'dashicons-format-image',
			80
		);
	}

	public function wig_admin_content() {
		?>

        <div class="wig-container">
			<?php
			if ( isset( $_POST['wig_delete_btn'] ) ) {
				$this->wig_delete_images();
				$this->wig_list_images();
			} else {
				$this->wig_list_images();
			}
			?>
        </div><!-- .wig-container -->

		<?php
	}

	/**
	 * @param $bytes
	 * @param int $decimals
	 *
	 * @return string
	 *
	 * Return file size of images
	 */
	public function wig_file_size( $bytes, $decimals = 2 ) {
		$size   = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
		$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

		return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
	}

	/**
	 * @param bool $query
	 *
	 * @return array|null|object|string
	 *
	 * Query for imported images, return false for only count results
	 */
	public function wig_query_images( $query = true ) {
		global $wpdb;
		if ( $query ) {
			$result = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_title LIKE 'auto_thumbnail_%' ORDER BY post_date DESC LIMIT " . $this->wig_image_pagination( true ) . ",$this->post_per_page;" );
		} else {
			$result = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_title LIKE 'auto_thumbnail_%'" );
		}

		return $result;
	}

	/**
	 * List imported images
	 */
	public function wig_list_images() {

		$images = $this->wig_query_images();

		$template = '<form method="post" action="">';
		$template .= '<table class="wig-table">';
		$template .= '<thead>';
		$template .= '<tr>';
		$template .= '<th width="80px">' . __( 'Thumbnail', 'wig' ) . '</th>';
		$template .= '<th width="80px">' . __( 'Size', 'wig' ) . '</th>';
		$template .= '<th>' . __( 'Date', 'wig' ) . '</th>';
		$template .= '<th>' . __( 'Parent', 'wig' ) . '</th>';
		$template .= '<th><input type="checkbox" class="wig-delete-all">' . __( 'Delete', 'wig' ) . '</th>';
		$template .= '</tr>';
		$template .= '</thead>';
		$template .= '<tbody>';

		foreach ( $images as $image ) {
			$template .= '<tr>';
			$template .= '<td>';
			$template .= '<a href="' . wp_get_attachment_url( $image->ID ) . '" target="_blank">';
			$template .= '<img src="' . wp_get_attachment_url( $image->ID ) . '" width="100%" height="30px">';
			$template .= '</a>';
			$template .= '</td>';
			$template .= '<td>' . $this->wig_file_size( filesize( get_attached_file( $image->ID ) ) ) . '</td>';
			$template .= '<td width="90px">' . get_the_date( __( 'Y-m-d', 'wig' ), $image->ID ) . '</td>';
			$template .= '<td style="text-align:left;">';
			$template .= ( empty( $image->post_parent ) ? __( 'Detached', 'wig' ) : '<a href="' . get_permalink( $image->post_parent ) . '" target="_blank">' . get_the_title( $image->post_parent ) . '</a>' );
			$template .= '</td>';
			$template .= '<td><input type="checkbox" class="wig-delete-image" name="delete_image_id[]" value="' . $image->ID . '"></td>';
			$template .= '</tr>';
		}

		$template .= '</tbody>';
		$template .= '<tfoot>';
		$template .= '<tr>';
		$template .= '<td colspan="5" style="text-align:right;"><strong>' . __( 'Total Images: ', 'wig' ) . $this->wig_query_images( false ) . '</strong></td>';
		$template .= '</tr>';
		$template .= '</tfoot>';
		$template .= '</table>';
		$template .= '<input class="wig-btn" type="submit" name="wig_delete_btn" value="' . __( 'Delete', 'wig' ) . '">';
		$template .= '</form>';

		$template .= '<div class="wig-pagination">';
		$template .= $this->wig_image_pagination();
		$template .= '</div>';

		echo $template;
	}

	/**
	 * Delete imported images
	 */
	public function wig_delete_images() {
		echo '<div class="wig-alert">';
		foreach ( $_POST['delete_image_id'] as $del ) {
			$title = get_the_title( $del );
			printf( /* translators: %s: Name of attach */
				__( '<p><strong>File deleted: </strong>%s</p>', 'fw_img' ), $title );
			wp_delete_attachment( $del );
		}
		echo '</div>';
	}

	/**
	 * @param bool $mode_offset
	 *
	 * @return array|float|int|string
	 *
	 * Return pagination for images list
	 */
	public function wig_image_pagination( $mode_offset = false ) {
		$page   = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$total  = $this->wig_query_images( false );
		$offset = ( $page * $this->post_per_page ) - $this->post_per_page;

		$paginate = paginate_links( array(
			'base'      => add_query_arg( 'cpage', '%#%' ),
			'format'    => '',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'total'     => ceil( $total / $this->post_per_page ),
			'current'   => $page,
			'type'      => 'list'
		) );

		if ( ! $mode_offset ) {
			return $paginate;
		} else {
			return $offset;
		}
	}
}

new WP_Image_Graber();