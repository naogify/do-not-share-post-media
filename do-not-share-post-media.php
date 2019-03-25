<?php
/**
 * Plugin Name:     Do Not Share Post Media
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          Naoki Ohashi
 * Author URI:      https://naoki-is.me/
 * Text Domain:     do-not-share-post-media
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Do_Not_Share_Post_Media
 */

/**
 *`job_posts` 投稿タイプのサブメニューに、設定ページを追加。
 */
function dnspm_add_setting_menu() {
	$custom_page = add_submenu_page(
		'/options-general.php',
		__( 'Do not Share Posts and Media Settings', 'do-not-share-post-media' ),
		__( 'Do not Share Posts and Media Settings', 'do-not-share-post-media' ),
		'activate_plugins',
		'dnspm_settings',
		'dnspm_render_settings'
	);
}

add_action( 'admin_menu', 'dnspm_add_setting_menu' );

/**
 *設定ページの中身を表示。
 */
function dnspm_render_settings() {

	//保存処理をページに追加
	dnspm_save_data();

	$dnspm_common_hiringOrganization_name = get_option( 'dnspm_common_hiringOrganization_name' );
	$dnspm_common_hiringOrganization_url  = get_option( 'dnspm_common_hiringOrganization_url' );
	$dnspm_common_hiringOrganization_logo = get_option( 'dnspm_common_hiringOrganization_logo' );

	if ( ! isset( $dnspm_common_hiringOrganization_name ) ) {
		$dnspm_common_hiringOrganization_name = '';
	}
	if ( ! isset( $dnspm_common_hiringOrganization_url ) ) {
		$dnspm_common_hiringOrganization_url = '';
	}
	if ( ! isset( $dnspm_common_hiringOrganization_logo ) ) {
		$dnspm_common_hiringOrganization_logo = '';
	}

	echo '<h1>' . __( 'Do not Share Posts and Media Settings', 'do-not-share-post-media' ) . '</h1>';
	echo '<form method="post" action="">';
	wp_nonce_field( 'standing_on_the_shoulder_of_giants', '_nonce_dnspm' );
	echo '<h2>' . __( 'User roles do not share', 'do-not-share-post-media' ) . '</h2>';
	dnspm_user_roles_do_not_share();
	echo '<h2>' . __( 'Post types do not share', 'do-not-share-post-media' ) . '</h2>';
	dnspm_post_types_do_not_share();
	echo '<input type="submit" value="Save Changes">';
	echo '</form>';


}

function dnspm_save_data() {

	// nonce
	if ( ! isset( $_POST['_nonce_dnspm'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['_nonce_dnspm'], 'standing_on_the_shoulder_of_giants' ) ) {
		return;
	}

	$args       = array(
		'public' => true,
	);
	$post_types = get_post_types( $args, 'object' );

	foreach ( $post_types as $key => $value ) {
		if ( $key != 'attachment' ) {

			$name = 'dnspm_user_roles_customfields' . $key;

			if ( isset( $_POST[ $name ] ) ) {
				update_option( $name, $_POST[ $name ] );
			} else {
				update_option( $name, 'false' );
			}
		}
	}
}

function dnspm_user_roles_do_not_share() {

	$user_roles = get_editable_roles() ;

	echo '<ul>';
	foreach ( $user_roles as $key => $value ) {
			$checked_saved = get_option( 'dnspm_user_roles_customfields' . $key );
			$checked       = ( isset( $checked_saved ) && $checked_saved == 'true' ) ? ' checked' : '';
			echo '<li><label>';
			echo '<input type="checkbox" name="dnspm_user_roles_customfields' . $key . '" value="true"' . $checked . ' />' . esc_html( $value['name'] );
			echo '</label></li>';
	}
	echo '</ul>';
}

function dnspm_post_types_do_not_share() {

	$args       = array(
		'public' => true,
	);
	$post_types = get_post_types( $args, 'object' );

	echo '<ul>';
	foreach ( $post_types as $key => $value ) {
		if ( $key != 'page' ) {

			$checked_saved = get_option( 'dnspm_post_type_customfields' . $key );
			$checked       = ( isset( $checked_saved ) && $checked_saved == 'true' ) ? ' checked' : '';
			echo '<li><label>';
			echo '<input type="checkbox" name="dnspm_post_type_customfields' . $key . '" value="true"' . $checked . ' />' . esc_html( $value->label );
			echo '</label></li>';
		}
	}
	echo '</ul>';
}

function dnspm_add_theme_caps() {
	$role = get_role( 'contributor' );
	// これは、クラスインスタンスにアクセスする場合のみ機能します。
	// 現在のテーマにおいてのみ、投稿者は他の人の投稿を編集することができます。
	$role->add_cap( 'upload_files' );
}
add_action( 'admin_init', 'dnspm_add_theme_caps' );

function dnspm_display_only_self_uploaded_medias( $query ) {
	if ( ( $user = wp_get_current_user() ) && ! current_user_can( 'administrator' ) ) {
		$query['author'] = $user->ID;
	}
	return $query;
}
add_action( 'ajax_query_attachments_args', 'dnspm_display_only_self_uploaded_medias' );


/**
 * 管理画面の投稿一覧をログイン中のユーザーの投稿のみに制限します。(管理者以外)
 */
function dnspm_pre_get_author_posts( $query ) {

	// 管理画面 かつ 非管理者 かつ メインクエリ
	// かつ authorパラメータがないかauthorパラメータが自分のIDの場合、
	// 投稿者を絞った状態を前提として表示を調整します。
	if ( is_admin() && !current_user_can('administrator') && $query->is_main_query()
	     && ( !isset($_GET['author']) || $_GET['author'] == get_current_user_id() ) ) {
		// クエリの条件を追加
		$query->set( 'author', get_current_user_id() );
		// 値があると WP_Posts_List_Table#is_base_request() がfalseになり
		// 「すべて」のリンクが選択表示にならないため削除
		unset($_GET['author']);
	}
}
add_action( 'pre_get_posts', 'dnspm_pre_get_author_posts' );

/**
 * ログイン中のユーザーが投稿した投稿数を取得します。
 * ※ wp_count_posts()をベースにして下記を行っています。
 * 1. フィルターの削除 (無限ループを防ぐ)
 * 2. キャッシュキーの変更 (変更が反映されない問題を防ぐ)
 * 3. SQLの変更
 */
function dnspm_count_author_posts( $counts, $type = 'post', $perm = '' ) {
	// 管理画面側ではない場合、または管理画面側でも管理者の場合は投稿数を調整せず終了します。
	if ( !is_admin() || current_user_can('administrator') ) {
		return $counts;
	}

	global $wpdb;
	if ( ! post_type_exists( $type ) )
		return new stdClass;

	$cache_key = _count_posts_cache_key( $type, $perm ) . '_author'; // 2
	$counts = wp_cache_get( $cache_key, 'counts' );
	if ( false !== $counts ) {
		return $counts; // 1
	}

	// 3
	$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
	$query .= $wpdb->prepare( " AND ( post_author = %d )", get_current_user_id() );
	$query .= ' GROUP BY post_status';

	$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
	$counts = array_fill_keys( get_post_stati(), 0 );
	foreach ( $results as $row ) {
		$counts[ $row['post_status'] ] = $row['num_posts'];
	}
	$counts = (object) $counts;
	wp_cache_set( $cache_key, $counts, 'counts' );
	return $counts; // 1
}
add_filter( 'wp_count_posts', 'dnspm_count_author_posts', 10, 3 );
