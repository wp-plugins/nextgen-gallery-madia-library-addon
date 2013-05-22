/*This file is part of NextGEN Gallery Media Library Addon.NextGEN Gallery Media Library Addon is free software: you can redistribute it and/or modifyit under the terms of the GNU General Public License as published bythe Free Software Foundation, either version 3 of the License, or(at your option) any later version.NextGEN Gallery Media Library Addon is distributed in the hope that it will be useful,but WITHOUT ANY WARRANTY; without even the implied warranty ofMERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See theGNU General Public License for more details.You should have received a copy of the GNU General Public Licensealong with Foobar.  If not, see <http://www.gnu.org/licenses/>.*/<?php
if (!defined('ABSPATH') ||
    preg_match('#' . basename(__FILE__) . '#',
               $_SERVER['PHP_SELF'])
) {
	die("You are not allowed to call this page directly.");
}
require_once(WP_PLUGIN_DIR . '/nextgen-gallery-media-library-addon/config.php');
if (!class_exists('NextGENMediaLibGallery')) {
class NextGENMediaLibGallery {
	/**
	 * The list of galleries
	 * @var array
	 */
	private $gallery_list;

	/**
	 * Gallery default path
	 * @var string
	 */
	private $default_path;

	public function NextGENMediaLib() {
		$this->__construct();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add links to plugin's meta (in the plugins list)
		add_filter('plugin_row_meta',
		           array(&$this, 'add_plugin_links'),
		           10,
		           2
		);
		// Load addon if NextGEN Gallery plugin is installed/activated.
		if ($this->check_ngg()) {
			$this->init_includes();
			$this->init_properties();
			$this->init_hooks();
		}
	}

	/**
	 * Checks if required plugin is activated or not.
	 * @return boolean True, if required plugin is activated, false otherwise.
	 */
	public function check_ngg() {
		if (!$this->is_plugin_active(NGG_PLUGIN)) {
			add_action('admin_notices',
			           array(&$this, 'required_plugin_notice')
			); // works on single site
			add_action('all_admin_notices',
			           array(&$this, 'required_plugin_notice')
			); // works on sitewide network
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice/s if required plugin is not activated.
	 */
	public function required_plugin_notice() {
		echo "<div id='message' class='error'><p>" . NGGML_PLUGIN_NAME . " requires " .
		     NGG_PLUGIN_NAME . " to be installed and activated " .
		     "first before it can work properly.</p></div>";
	}

	/**
	 * Append additional links for our plugin in the plugins' list.
	 *
	 * @param array  $links Default links generated by Wordpress.
	 * @param string $file  The path string of current plugin file.
	 *
	 * @return array Modified array of links.
	 */
	public function add_plugin_links($links, $file) {
		if ($file == NGGML_PLUGIN_FILE) {
			$links[] = '<a href="http://jaggededgemedia.com/plugins/nextgen-gallery-media-library-addon/">Plugin Page</a>';
		}

		return $links;
	}

	/**
	 * Registers main admin page.
	 */
	public function register_admin_page() {
		add_menu_page(
			"NGG Media Lib",
			"NGG Media Lib",
			"administrator",
			basename(dirname(__FILE__)) . '/admin-page.php',
			'',
			path_join(NGGALLERY_URLPATH,
			          'admin/images/nextgen_16_color.png'
			)
		);
	}

	/**
	 * Loads additional scripts
	 */
	public function admin_addl_scripts() {
		if (get_current_screen()->id == 'gallery_page_nggallery-add-gallery') {
			wp_enqueue_media();
			add_action('admin_head',
			           array(&$this, 'NGGML_styles')
			);
			add_action('admin_footer',
			           array(&$this, 'medialibrary_js')
			);
		}
	}

	/**
	 * Inline styles in admin_head
	 */
	public function NGGML_styles() {
		?>
		<style>
			.NGGML-error {
				background-color: #FFEBE8;
				border-color: #CC0000;
				border-radius: 3px 3px 3px 3px;
				border-style: solid;
				border-width: 1px;
				display: none;
				padding: 0 0.6em;
				margin: 5px 0 15px;
			}

			#NGGML-images-preview ul li {
				float: left;
				margin: 0 10px;
			}
		</style>
	<?php
	}

	/**
	 * Appends additional tab/s.
	 *
	 * @param array $tabs Default tabs from NextGEN Gallery.
	 *
	 * @return array The modified array of $tabs
	 */
	public function add_ngg_tab($tabs) {
		$tabs['frommedialibrary'] = 'Add Images From Media Library';

		return $tabs;
	}

	/**
	 * Registers custom taxonomy for use as media tags
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                       => _x('Media Tags', 'taxonomy general name'),
			'singular_name'              => _x('Media Tag', 'taxonomy singular name'),
			'search_items'               => __('Search Media Tags'),
			'popular_items'              => __('Popular Media Tags'),
			'all_items'                  => __('All Media Tags'),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __('Edit Media Tag'),
			'update_item'                => __('Update Media Tag'),
			'add_new_item'               => __('Add New Media Tag'),
			'new_item_name'              => __('New Media Tag Name'),
			'separate_items_with_commas' => __('Separate media tags with commas'),
			'add_or_remove_items'        => __('Add or remove media tags'),
			'choose_from_most_used'      => __('Choose from the most used media tags'),
			'not_found'                  => __('No media tags found.'),
			'menu_name'                  => __('Media Tags')
		);
		$args   = array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_in_nav_menus'     => true,
			'show_tagcloud'         => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_generic_term_count',
			'query_var'             => true,
			'rewrite'               => array('slug' => 'NGGML-media-tags')
		);
		register_taxonomy(NGGML_MEDIA_TAGS_QUERYVAR, 'attachment', $args);
	}

	public function search_media_tags($query) {
		if (!is_admin()) {
			return;
		}
		$current_screen = get_current_screen();
		if (empty($current_screen) && $query->query['post_type'] == 'attachment' && $query->is_search) {
			$args  = array(
				'fields' => 'names',
				'search' => $query->get('s')
			);
			$terms = get_terms(array(NGGML_MEDIA_TAGS_QUERYVAR), $args);
			if (is_wp_error($terms)) {
				return;
			}
			$query->set('post_status', 'inherit');
			$query->set('tax_query', array(
			                              array(
				                              'taxonomy' => NGGML_MEDIA_TAGS_QUERYVAR,
				                              'field'    => 'slug',
				                              'terms'    => $terms
			                              )
			                         )
			);

			echo "<pre>" . print_r($query, true) . "</pre>";
		}
	}

	/**
	 * The "Add Images From Media Library" content.
	 */
	public function tab_frommedialibrary() {
		?>
		<h2>Add Images To Your Gallery From Media Library</h2>
		<form id="NGGML-selected-images-form" action="" method="POST">
			<div id="select-gallery"><label for="">Add images to:</label>
				<select id="togallery" name="togallery">
					<option value="0"><?php
						_e('Choose gallery',
						   'nggallery'
						)
						?>
					</option>
					<option value="new">New Gallery</option>
					<?php
					foreach ($this->gallery_list as $gallery) {
						//special case : we check if a user has this cap, then we override the second cap check
						if (!current_user_can('NextGEN Upload in all galleries')) {
							if (!nggAdmin::can_manage_this_gallery($gallery->author)) {
								continue;
							}
						}
						$name = (empty($gallery->title))
							? $gallery->name
							: $gallery->title;
						echo '<option value="' . $gallery->gid . '" >' . $gallery->gid . ' - ' . esc_attr($name
						) . '</option>' . "\n";
					}
					?>
				</select>
				<input id="togallery_name" name="togallery_name" type="text" size="30" value=""
				       style="display: none;" />
				<span id="gallery-error" class="NGGML-error"></span>
			</div>
			<p><a id="NGGML-select-images" class="button-secondary" href="#">Select Images</a> <span id="image-error"
			                                                                                        class="NGGML-error"></span>
			</p>

			<div id="NGGML-images-preview"></div>

			<div id="NGGML-selected-images"></div>
			<p style="clear: both;">
				<input id="NGGML-submit-images" class="button-primary" type="submit" value="Add to Gallery" />
                <span id="copying" style="display: none;">Copying... <img
		                src="<?php echo plugins_url('nextgen-gallery/images/ajax-loader.gif'); ?>"
		                alt="Copying..." /></span>
			</p>
		</form>
	<?php
	}

	/**
	 * Ajax handler.
	 * Sends request for creating a gallery and or
	 * adding images to a gallery from media library
	 */
	public function ajax_lib_to_ngg() {
		check_ajax_referer('lib-to-ngg-nonce',
		                   'NGGML_nonce'
		);
		$msg        = new stdClass();
		$msg->error = false;
		if (!isset($_POST['togallery']) || $_POST['togallery'] === '0') {
			$msg->error         = true;
			$msg->error_code    = 'gallery_error';
			$msg->error_message = 'No gallery selected!';
			echo json_encode($msg);
			die();
		} else {
			if (!empty($_POST['togallery']) && $_POST['togallery'] === 'new' && empty($_POST['togallery_name'])) {
				$msg->error         = true;
				$msg->error_code    = 'gallery_error';
				$msg->error_message = 'Enter a name for the new gallery!';
				echo json_encode($msg);
				die();
			} else {
				if (empty($_POST['imagefiles'])) {
					$msg->error         = true;
					$msg->error_code    = 'image_error';
					$msg->error_message = 'No images selected!';
					echo json_encode($msg);
					die();
				}
			}
		}
		if (isset($_POST['imagefiles'])) {
			$galleryID = 0;
			if ($_POST['togallery'] == 'new') {
				if (!nggGallery::current_user_can('NextGEN Add new gallery')) {
					$msg->error         = true;
					$msg->error_code    = 'ngg_error';
					$msg->error_message = 'No cheating!';
					echo json_encode($msg);
					die();
				} else {
					$newgallery = esc_attr($_POST['togallery_name']);
					if (!empty($newgallery)) {
						$galleryID = nggAdmin::create_gallery($newgallery,
						                                      $this->default_path,
						                                      false
						);
					}
				}
			} else {
				$galleryID = (int)$_POST['togallery'];
			}
			foreach ($_POST['imagefiles'] as $img_url) {
				//$img_urls[] = urldecode($img_url);
				$this->add_to_superglobal_files('imagefiles',
				                                urldecode($img_url)
				);
			}
			echo json_encode($this->transfer_images_from_library_to_ngg($galleryID));
			die();
		}
		$msg->error           = true;
		$msg->error_code[]    = 'upload_error';
		$msg->error_message[] = 'Image upload error!';
		echo json_encode($msg);
		die();
	}

	/**
	 * Add to $_FILES from external url
	 * sample usage:
	 * <code>
	 * add_to_superglobal_files('google_favicon', 'http://google.com/favicon.ico');
	 * </code>
	 *
	 * @param string $key
	 * @param string $url sample http://some.tld/path/to/file.ext
	 */
	public function add_to_superglobal_files($key, $url) {
		$temp_name     = tempnam(sys_get_temp_dir(),
		                         'NGGML'
		);
		$original_name = basename(parse_url($url,
		                                    PHP_URL_PATH
		                          )
		);
		$img_raw_data  = file_get_contents($url);
		file_put_contents($temp_name,
		                  $img_raw_data
		);
		$type           = wp_check_filetype_and_ext($temp_name,
		                                            $original_name
		);
		$_FILES[$key][] = array(
			'name'     => $original_name,
			'type'     => $type['type'],
			'tmp_name' => $temp_name,
			'error'    => 0,
			'size'     => strlen($img_raw_data)
		);
	}

	/**
	 * Processes adding of images to a gallery.
	 *
	 * @param int $galleryID The gallery id to add images to.
	 *
	 * @return stdClass Response message when transferring
	 * images from library to NGG
	 */
	public function transfer_images_from_library_to_ngg($galleryID) {
		global $nggdb;
		$msg        = new stdClass();
		$msg->error = false;
		// Images must be an array
		$imageslist = array();
		// get the path to the gallery
		$gallery = $nggdb->find_gallery($galleryID);
		if (empty($gallery->path)) {
			$msg->error           = true;
			$msg->error_code[]    = 'gallery_path_error';
			$msg->error_message[] = __('Failure in database, no gallery path set !',
			                           'nggallery'
			);

			return $msg;
		}
		// read list of images
		$dirlist    = nggAdmin::scandir($gallery->abspath);
		$imagefiles = $_FILES['imagefiles'];
		//die("<pre>" . print_r($imagefiles, true) . "</pre>");
		$imagefiles_count = 0;
		if (is_array($imagefiles)) {
			foreach ($imagefiles as $key => $value) {
				// look only for uploded files
				if ($imagefiles[$key]['error'] == 0) {
					$temp_file = $imagefiles[$key]['tmp_name'];
					//clean filename and extract extension
					$filepart = nggGallery::fileinfo($imagefiles[$key]['name']);
					$filename = $filepart['basename'];
					// check for allowed extension and if it's an image file
					$ext = array('jpg', 'png', 'gif');
					if (!in_array($filepart['extension'],
					              $ext
					) || !@getimagesize($temp_file)
					) {
						$msg->error           = true;
						$msg->error_code[]    = 'not_an_image_error';
						$msg->error_message[] = esc_html($imagefiles[$key]['name']);
						continue;
					}
					// check if this filename already exist in the folder
					$i = 0;
					while (in_array($filename,
					                $dirlist
					)) {
						$filename = $filepart['filename'] . '_' . $i++ . '.' . $filepart['extension'];
					}
					$dest_file = $gallery->abspath . '/' . $filename;
					//check for folder permission
					if (!is_writeable($gallery->abspath)) {
						$message              = sprintf(__('Unable to write to directory %s. Is this directory writable by the server?',
						                                   'nggallery'
						                                ),
						                                esc_html($gallery->abspath)
						);
						$msg->error           = true;
						$msg->error_code[]    = 'write_permission_error';
						$msg->error_message[] = $message;

						return $msg;
					}
					// save temp file to gallery
					if (!copy($temp_file,
					          $dest_file
					)
					) {
						$msg->error           = true;
						$msg->error_code[]    = 'not_an_image_error';
						$msg->error_message[] = __('Error, the file could not be moved to : ',
						                           'nggallery'
						                        ) . esc_html($dest_file);
						$safemode             = $this->check_safemode($gallery->abspath);
						if ($safemode) {
							$msg->error           = true;
							$msg->error_code[]    = $safemode->error_code;
							$msg->error_message[] = $safemode->error_message;
						}
						continue;
					}
					if (!nggAdmin::chmod($dest_file)) {
						$msg->error           = true;
						$msg->error_code[]    = 'set_permissions_error';
						$msg->error_message[] = __('Error, the file permissions could not be set',
						                           'nggallery'
						);
						continue;
					}
					// add to imagelist & dirlist
					$imageslist[] = $filename;
					$dirlist[]    = $filename;
				}
				$imagefiles_count++;
			}
		}
		if (count($imageslist) > 0) {
			// add images to database
			$image_ids = nggAdmin::add_Images($galleryID,
			                                  $imageslist
			);
			//create thumbnails
			foreach ($image_ids as $image_id) {
				nggAdmin::create_thumbnail($image_id);
			}
			$msg->success         = true;
			$msg->success_message = count($image_ids) . __(' Image(s) successfully added',
			                                               'nggallery'
			);
			$msg->success_message .= " to $gallery->title";

			return $msg;
		}
		$msg->error           = true;
		$msg->error_code[]    = 'transfer_error';
		$msg->error_message[] = sprintf('Error in transferring selected %s.',
		                                ($imagefiles_count > 1)
			                                ? 'images'
			                                : 'image'
		);

		return $msg;
	}

	/**
	 * Check UID in folder and Script
	 * (Adapted from NGG)
	 * Read http://www.php.net/manual/en/features.safe-mode.php to understand safe_mode
	 *
	 * @param string $foldername The name of the folder
	 *
	 * @return bool $result True if in safemode, False otherwise.
	 */
	public function check_safemode($foldername) {
		$msg        = new stdClass();
		$msg->error = false;
		if (SAFE_MODE) {
			$script_uid = (ini_get('safe_mode_gid'))
				? getmygid()
				: getmyuid();
			$folder_uid = fileowner($foldername);
			if ($script_uid != $folder_uid) {
				$message = sprintf(__('SAFE MODE Restriction in effect! You need to create the folder <strong>%s</strong> manually',
				                      'nggallery'
				                   ),
				                   esc_html($foldername)
				);
				$message .= '<br />' .
				            sprintf(__('When safe_mode is on, PHP checks to see if the owner (%s) of the current script matches the owner (%s) of the file to be operated on by a file function or its directory',
				                       'nggallery'
				                    ),
				                    $script_uid,
				                    $folder_uid
				            );
				$msg->error         = true;
				$msg->error_code    = 'safe_mode_error';
				$msg->error_message = $message;

				return $msg;
			}
		}

		return false;
	}

	/**
	 * Script that launches/processes the media library dialog.
	 */
public function medialibrary_js() {
	$ajax_nonce = wp_create_nonce('lib-to-ngg-nonce');
	?>
	<script id="medialibrary_js">
		jQuery(document).ready(function () {
			var custom_uploader;

			jQuery('#NGGML-select-images').click(function (e) {
				e.preventDefault();
				if (custom_uploader) {
					custom_uploader.open();
					return;
				}

				//Extend the wp.media object
				custom_uploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose Image',
					button: {
						text: 'Choose Image'
					},
					library: {
						type: 'image'
					},
					selection: {
					},
					multiple: true
				});

				//When a file is selected, grab the URL and set it as the text field's value
				custom_uploader.on('select', function () {
					var attachment = custom_uploader.state()
						.get('selection')
						.toJSON();

					var images_preview = '<p class="label"><label>Preview</label></p><ul>',
						i = 0,
						image_ids = '';
					//jQuery('#upload_image').val(attachment.url);

					while (i < attachment.length) {
						images_preview += "<li><img src='" + attachment[i].sizes.thumbnail.url + "' alt='' /></li>";
						image_ids += "<input data-imgid='" + attachment[i].id + "' type='hidden' name='imagefiles[]' value='" + encodeURIComponent(attachment[i].sizes.full.url) + "' />";
						i++;
					}
					images_preview += '</ul>';
					jQuery('#NGGML-selected-images').html(image_ids);
					jQuery('#NGGML-images-preview').html(images_preview);
				});

				// Check already selected images when form opens
				custom_uploader.on('open', function () {
					var selection = custom_uploader.state().get('selection');
					var ids = jQuery('#NGGML-selected-images input').map(function () {
						return jQuery(this).attr('data-imgid');
					}).get();
					ids.forEach(function (id) {
						var attachment = wp.media.attachment(id);
						//attachment.fetch();
						selection.add(attachment
							? [attachment]
							: []);
					});
				});

				//Open the uploader dialog
				custom_uploader.open();
			});

			// Show gallery name input if 'new' is currently selected
			if (jQuery('#togallery').val() == 'new') {
				jQuery('#togallery_name').show();
			}

			jQuery('#togallery').on('change', function () {
				var $this = jQuery(this);
				if ($this.val() == 'new') {
					jQuery('#togallery_name').show();
				} else {
					jQuery('#togallery_name').hide();
				}
			});

			// Ajax POST
			jQuery('#NGGML-selected-images-form').submit(function (e) {
				e.preventDefault();
				var copying = jQuery('#copying');
				jQuery('#wpbody-content #screen-meta').next('div.wrap').remove();
				var data = {
					action: 'lib_to_ngg',
					NGGML_nonce: '<?php echo $ajax_nonce; ?>',
					togallery: jQuery(this).find('#togallery').val(),
					imagefiles: jQuery(this)
						.find('input[type="hidden"]')
						.map(function () {
							return jQuery(this).val();
						}).get()
				};
				if (jQuery('#togallery').val() == 'new') {
					data['togallery_name'] = jQuery(this).find('#togallery_name').val();
				}
				copying.show();
				jQuery.post(ajaxurl,
					data,
					function (response) {
						response = JSON.parse(response);
						if (response.error) {
							if (response.error_code == 'gallery_error') {
								jQuery('#gallery-error')
									.html(response.error_message)
									.show();
								setTimeout(function () {
									jQuery('#gallery-error')
										.fadeOut('slow', function () {
											jQuery(this).html('');
										});
								}, 3000);
							} else if (response.error_code == 'image_error') {
								jQuery('#image-error')
									.html(response.error_message)
									.show();
								setTimeout(function () {
									jQuery('#image-error')
										.fadeOut('slow', function () {
											jQuery(this).html('');
										});
								}, 3000);
							} else {
								var error_message = '';
								if (jQuery.isArray(response.error_message)) {
									jQuery.each(response.error_message, function (index, value) {
										error_message += '<p>' + value + '</p>';
									});
								} else {
									error_message += '<p>' + response.error_message + '</p>';
								}
								jQuery('#wpbody-content #screen-meta').after('<div class="wrap"><h2></h2><div id="error" class="error below-h2">' + error_message + '</div></div>');
							}
						} else {
							jQuery('#wpbody-content #screen-meta').after('<div class="wrap"><h2></h2><div class="updated fade" id="message"><p>' + response.success_message + '</p></div></div>');
							copying.hide();
						}
					});
			});
		});
	</script>
<?php
}

	/**
	 * For debugging only
	 *
	 * TODO
	 */
	public function dumpcontents() {
//		global $wp_query;
		$args = array(
			'fields' => 'names',
			'search' => 'a'
		);
		$terms = get_terms(array(NGGML_MEDIA_TAGS_QUERYVAR), $args);
		if (is_wp_error($terms)) {
			echo "<pre>" . print_r($terms, true) . "</pre>";
		}

		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'tax_query' => array(
				array(
					'taxonomy' => NGGML_MEDIA_TAGS_QUERYVAR,
					'field' => 'slug',
					'terms' => $terms
				)
			)
		);
		$wp_query = new WP_Query($args);
		echo "<pre>" . print_r($wp_query, true) . "</pre>";
	}

	/*
	  |-------------------------------------------
	  |               PRIVATE ACCESS
	  |-------------------------------------------
	 */
	/**
	 * Add filters/actions
	 */
	private function init_hooks() {
//		add_action('admin_notices', array(&$this, 'dumpcontents'));
		// Add an administration menu (uncomment to register admin page)
		/* add_action('admin_menu',
		  array(&$this, 'register_admin_page')
		  ); */
		// Add new tab/s in nggallery-add-gallery page
		add_filter('ngg_addgallery_tabs', array(&$this, 'add_ngg_tab'));
		// Add tab/s call back
		add_action('ngg_tab_content_frommedialibrary', array(&$this, 'tab_frommedialibrary'));
		// Add additional script/s
		add_action('admin_enqueue_scripts', array(&$this, 'admin_addl_scripts'));
		// Add ajax handler
		add_action('wp_ajax_lib_to_ngg', array(&$this, 'ajax_lib_to_ngg'));
		// Register NGGML_media_tags taxonomy
		add_action('init', array(&$this, 'register_taxonomy'));
		// Add NGGML_media_tags in image search
		add_action('pre_get_posts', array(&$this, 'search_media_tags'), 999);
	}

	/**
	 * Initialize class members/properties.
	 * @global object $ngg   NextGEN Gallery loader object.
	 * @global object $nggdb NextGEN Gallery database object.
	 */
	private function init_properties() {
		global $ngg, $nggdb;
		$this->gallery_list = $nggdb->find_all_galleries('gid',
		                                                 'DESC'
		);
		$this->default_path = $ngg->options['gallerypath'];
	}

	/**
	 * Require files from NGG
	 */
	private function init_includes() {
		require_once(WP_PLUGIN_DIR . '/nextgen-gallery/admin/functions.php');
	}

	/**
	 * Similar to WP's is_plugin_active function.
	 * Check whether the plugin is active by checking the active_plugins list.
	 *
	 * @param string $plugin Base plugin path from plugins directory.
	 *
	 * @return bool True, if in the active plugins list. False, not in the list.
	 */
	private function is_plugin_active($plugin) {
		return in_array($plugin,
		                (array)get_option('active_plugins', array())) || $this->is_plugin_active_for_network($plugin);
	}

	/**
	 * Similar to WP's is_plugin_active_for_network function.
	 * Check whether the plugin is active for the entire network.
	 *
	 * @param string $plugin Base plugin path from plugins directory.
	 *
	 * @return boolean True, if active for the network, otherwise false.
	 */
	private function is_plugin_active_for_network($plugin) {
		if (!is_multisite()) {
			return false;
		}
		$plugins = get_site_option('active_sitewide_plugins');
		if (isset($plugins[$plugin])) {
			return true;
		}

		return false;
	}
}
}
