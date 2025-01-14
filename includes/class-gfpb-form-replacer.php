<?php
/**
 * Adds a Replace Forms tab to the Import/Export feature.
 *
 * @package Gravity_Forms_Power_Boost
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gravity_Forms_Power_Boost_Form_Replacer
 *
 * This class adds a tab to the Gravity Forms Import/Export page found at Forms
 * > Import/Export in the dashboard. The tab is named "Replace Forms" and works
 * similarly to "Import Forms" except that it updates existing forms based on ID
 * rather than always inserting duplicates.
 */
class GFPB_Form_Replacer {

	const EXPORT_TAB_SLUG = 'gforms_replacer';

	/**
	 * Adds hooks that power the form replacer feature.
	 *
	 * @return void
	 */
	public function add_hooks() {
		// Adds an item to the Import/Export menu tabs.
		add_filter( 'gform_export_menu', array( $this, 'add_replace_forms_tab' ) );

		// Populate the tab with content.
		add_action( 'gform_export_page_' . self::EXPORT_TAB_SLUG, array( $this, 'populate_replace_forms_tab' ) );
	}

	/**
	 * Adds a "Replace Forms" tab to the Import/Export settings page.
	 *
	 * @param  array $setting_tabs An array of tabs found at Settings > Import/Export.
	 * @return array
	 */
	public function add_replace_forms_tab( $setting_tabs ) {
		if ( ! class_exists( 'GFCommon' ) || ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			return;
		}

		$setting_tabs[] = array(
			'name'  => self::EXPORT_TAB_SLUG,
			'label' => __( 'Replace Forms', 'power-boost-for-gravity-forms' ),
		);
		return $setting_tabs;
	}

	/**
	 * Outputs HTML that populates the Replace Forms tab of the Import/Export
	 * page.
	 *
	 * @return void
	 */
	public function populate_replace_forms_tab() {
		if ( ! class_exists( 'GFCommon' ) || ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_die( 'You do not have permission to access this page' );
		}

		if ( isset( $_POST['import_forms'] ) ) {

			check_admin_referer( 'gf_replace_forms', 'gf_replace_forms_nonce' );

			if ( ! empty( $_FILES['gf_import_file']['tmp_name'][0] ) ) {

				// Set initial count to 0.
				$count       = 0;
				$forms_array = array();

				// Loop through each uploaded file.
				foreach ( array_map( 'sanitize_text_field', wp_unslash( $_FILES['gf_import_file']['tmp_name'] ) ) as $import_file_path ) {
					// Turn the file into an array of forms.
					$json = file_get_contents( $import_file_path );
					if ( false === $json ) {
						continue;
					}

					// Deserialize into an array of forms.
					$forms_array = json_decode( $json, true );

					// Are any of the form confirmations redirects?
					foreach ( $forms_array as $form ) {
						if ( empty( $form['id'] ) ) {
							continue;
						}

						foreach ( $form['confirmations'] as $key => $confirmation ) {
							if ( 'version' === $key ) {
								continue;
							}

							if ( 'redirect' !== $confirmation['type'] ) {
								continue;
							}

							$confirmation_config_url = admin_url(
								sprintf(
									'admin.php?page=gf_edit_forms&view=settings&subview=confirmation&id=%s&cid=%s',
									$form['id'],
									$confirmation['id']
								)
							);

							/**
							 * Ask the user if $confirmation['url'] is a URL
							 * that needs to be updated.
							 */
							GFCommon::add_message(
								sprintf(
									'%s \'%s\', %s %s, %s %s. <a href="%s">%s</a> %s.',
									__( 'A confirmation in form', 'power-boost-for-gravity-forms' ),
									$form['title'],
									__( 'ID', 'power-boost-for-gravity-forms' ),
									$form['id'],
									__( 'is set to redirect users to', 'power-boost-for-gravity-forms' ),
									$confirmation['url'],
									$confirmation_config_url,
									__( 'Click here', 'power-boost-for-gravity-forms' ),
									__( 'if this URL needs to be updated', 'power-boost-for-gravity-forms' )
								)
							);
						}
					}

					// Update the forms saved in this site.
					$count += self::update_forms( $forms_array );
				}

				if ( 0 === $count ) {
					$error_message = sprintf(
						'%s <a href="admin.php?page=gf_export&view=export_form">%s</a> %s',
						__( 'Forms could not be imported. Please make sure your files have the .json extension, and that they were generated by the', 'power-boost-for-gravity-forms' ),
						__( 'Gravity Forms Export form', 'power-boost-for-gravity-forms' ),
						__( 'tool.', 'power-boost-for-gravity-forms' )
					);
					GFCommon::add_error_message( $error_message );
				} elseif ( '-1' === $count ) {
					GFCommon::add_error_message( esc_html__( 'Forms could not be imported. Your export file is not compatible with your current version of Gravity Forms.', 'power-boost-for-gravity-forms' ) );
				} else {
					$form_text = $count > 1 ? esc_html__( 'forms', 'power-boost-for-gravity-forms' ) : esc_html__( 'form', 'power-boost-for-gravity-forms' );
					$edit_link = 1 === $count ? "<a href='admin.php?page=gf_edit_forms&id={$forms_array[0]['id']}'>" . esc_html__( 'Edit Form', 'power-boost-for-gravity-forms' ) . '</a>' : '';
					GFCommon::add_message(
						sprintf(
							'%s %d %s %s',
							esc_html__( 'Gravity Forms imported', 'power-boost-for-gravity-forms' ),
							$count,
							$form_text,
							__( 'successfully', 'power-boost-for-gravity-forms' )
						) . ". $edit_link"
					);
				}
			}
		}

		GFExport::page_header();

		?><div class="gform-settings__content">
			<form method="post" enctype="multipart/form-data" class="gform_settings_form">
				<?php wp_nonce_field( 'gf_replace_forms', 'gf_replace_forms_nonce' ); ?>
				<div class="gform-settings-panel gform-settings-panel--full">
					<header class="gform-settings-panel__header"><legend class="gform-settings-panel__title">
					<?php

					esc_html_e( 'Replace Forms', 'power-boost-for-gravity-forms' );

					?>
					</legend></header>
					<div class="gform-settings-panel__content">
						<div class="gform-settings-description">
						<?php
							printf(
								'%s <a href="admin.php?page=gf_export&view=export_form">%s</a> %s',
								esc_html__( 'Select the Gravity Forms export files you would like to import. Please make sure your files have the .json extension, and that they were generated by the', 'power-boost-for-gravity-forms' ),
								esc_html__( 'Gravity Forms Export form', 'power-boost-for-gravity-forms' ),
								esc_html__( 'tool. When you click the import button below, Gravity Forms will import the forms.', 'power-boost-for-gravity-forms' )
							);
						?>
						<p><b>
						<?php
							printf(
								'%s <a href="https://wordpress.org/plugins/power-boost-for-gravity-forms/">%s</a>.',
								esc_html__( 'This feature updates existing forms instead of creating duplicates like the Import Forms tab. Provided by', 'power-boost-for-gravity-forms' ),
								esc_html__( 'Power Boost for Gravity Forms', 'power-boost-for-gravity-forms' )
							);
						?>
						</b></p></div>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									<label for="gf_import_file"><?php esc_html_e( 'Select Files', 'power-boost-for-gravity-forms' ); ?></label> <?php gform_tooltip( 'import_select_file' ); ?>
								</th>
								<td><input type="file" name="gf_import_file[]" id="gf_import_file" multiple /></td>
							</tr>
						</table>
						<br /><br />
						<input type="submit" value="<?php esc_html_e( 'Import', 'power-boost-for-gravity-forms' ); ?>" name="import_forms" class="button large primary" />
					</div>
				</div>
			</form>
		</div>
		<?php

		GFExport::page_footer();
	}

	/**
	 * Overwrites existing forms when they are found to save the same ID as
	 * forms in the $forms_array parameter.
	 *
	 * @param  array $forms_array An array of forms.
	 * @return int The number of forms that were updated
	 */
	public static function update_forms( $forms_array ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return 0;
		}

		// Update the forms.
		$updated_count = 0;
		$imported_forms = array();
		foreach ( $forms_array as $form ) {
			if ( empty( $form['id'] ) ) {
				continue;
			}

			// does the form exist?
			$existing = GFAPI::get_form( $form['id'] );
			if ( false === $existing ) {
				// no, skip this one.
				continue;
			}

			// Delete all GravityFlow feeds or they will be duplicated.
			$feeds = GFAPI::get_feeds( null, $form['id'] );
			foreach ( $feeds as $feed ) {
				// Is this a GravityFlow feed?
				if ( 'gravityflow' === $feed['addon_slug'] ) {
					// Yes.
					$result = GFAPI::delete_feed( $feed['id'] );
				}
			}

			GFAPI::update_form( $form, $form['id'] );
			$updated_count++;

			/**
			 * GFAPI::update_form() marks all forms as in active. Restore the
			 * previous status.
			 */
			GFFormsModel::update_form_active( $form['id'], $existing['is_active'] );

			$imported_forms[ $form['id'] ] = $form;
		}

		if ( ! empty( $imported_forms ) ) {
			// Copied this from Gravity Forms so plugins like GravityFlow can manage their feeds.
			/**
			 * Fires after forms have been imported.
			 *
			 * Used to perform additional actions after import
			 *
			 * @param array $forms An array imported form objects.
			 */
			do_action( 'gform_forms_post_import', $imported_forms );
		}

		return $updated_count;
	}
}
